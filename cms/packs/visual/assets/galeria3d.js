/* cms_simple · visual/galeria3d — three.js se carga solo cuando la galería se acerca al viewport */
(function () {
  'use strict';
function initGallery3D(canvas) {
      if (!canvas || typeof THREE === 'undefined') return;
  
      var wrapper = canvas.parentElement;
  
      // Screenshot images
      var imagePaths = (canvas.dataset.images || '').split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      if (!imagePaths.length) {
          return;
      }
  
      // Settings
      var VISIBLE_COUNT = 10;
      var DEPTH_RANGE = 50;
      var HALF_RANGE = DEPTH_RANGE / 2;
      var AUTO_SPEED = 0.3;
  
      // Fade/blur settings
      var FADE_IN_START = 0.05, FADE_IN_END = 0.25;
      var FADE_OUT_START = 0.4, FADE_OUT_END = 0.43;
      var BLUR_IN_START = 0.0, BLUR_IN_END = 0.1;
      var BLUR_OUT_START = 0.4, BLUR_OUT_END = 0.43;
      var MAX_BLUR = 8.0;
  
      // Scene setup
      var scene = new THREE.Scene();
      var cw = canvas.clientWidth, ch = canvas.clientHeight;
      var camera = new THREE.PerspectiveCamera(55, cw / ch, 0.1, 100);
      camera.position.set(0, 0, 0);
  
      var renderer = new THREE.WebGLRenderer({ canvas: canvas, antialias: true, alpha: true });
      renderer.setSize(cw, ch);
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.setClearColor(0x000000, 0);
  
      // Load textures
      var loader = new THREE.TextureLoader();
      var textures = [];
      var loadedCount = 0;
  
      imagePaths.forEach(function(path, i) {
          loader.load(path, function(tex) {
              tex.minFilter = THREE.LinearFilter;
              tex.magFilter = THREE.LinearFilter;
              textures[i] = tex;
              loadedCount++;
              if (loadedCount === imagePaths.length) startGallery();
          });
      });
  
      // Cloth shader
      var vertexShader = [
          'uniform float scrollForce;',
          'uniform float time;',
          'uniform float isHovered;',
          'varying vec2 vUv;',
          '',
          'void main() {',
          '    vUv = uv;',
          '    vec3 pos = position;',
          '',
          '    float curveIntensity = scrollForce * 0.3;',
          '    float distanceFromCenter = length(pos.xy);',
          '    float curve = distanceFromCenter * distanceFromCenter * curveIntensity;',
          '',
          '    float ripple1 = sin(pos.x * 2.0 + scrollForce * 3.0) * 0.02;',
          '    float ripple2 = sin(pos.y * 2.5 + scrollForce * 2.0) * 0.015;',
          '    float clothEffect = (ripple1 + ripple2) * abs(curveIntensity) * 2.0;',
          '',
          '    float flagWave = 0.0;',
          '    if (isHovered > 0.5) {',
          '        float wavePhase = pos.x * 3.0 + time * 8.0;',
          '        float dampening = smoothstep(-0.5, 0.5, pos.x);',
          '        flagWave = sin(wavePhase) * 0.1 * dampening;',
          '        flagWave += sin(pos.x * 5.0 + time * 12.0) * 0.03 * dampening;',
          '    }',
          '',
          '    pos.z -= (curve + clothEffect + flagWave);',
          '    gl_Position = projectionMatrix * modelViewMatrix * vec4(pos, 1.0);',
          '}'
      ].join('\n');
  
      var fragmentShader = [
          'uniform sampler2D map;',
          'uniform float opacity;',
          'uniform float blurAmount;',
          'uniform float scrollForce;',
          'varying vec2 vUv;',
          '',
          'void main() {',
          '    vec4 color = texture2D(map, vUv);',
          '',
          '    if (blurAmount > 0.0) {',
          '        vec2 texelSize = 1.0 / vec2(textureSize(map, 0));',
          '        vec4 blurred = vec4(0.0);',
          '        float total = 0.0;',
          '        for (float x = -2.0; x <= 2.0; x += 1.0) {',
          '            for (float y = -2.0; y <= 2.0; y += 1.0) {',
          '                vec2 offset = vec2(x, y) * texelSize * blurAmount;',
          '                float weight = 1.0 / (1.0 + length(vec2(x, y)));',
          '                blurred += texture2D(map, vUv + offset) * weight;',
          '                total += weight;',
          '            }',
          '        }',
          '        color = blurred / total;',
          '    }',
          '',
          '    float curveHighlight = abs(scrollForce) * 0.05;',
          '    color.rgb += vec3(curveHighlight * 0.1);',
          '',
          '    gl_FragColor = vec4(color.rgb, color.a * opacity);',
          '}'
      ].join('\n');
  
      // State
      var scrollVelocity = 0;
      var autoPlay = true;
      var lastInteraction = 0;
      var planes = [];
      var meshes = [];
      var materials = [];
      var clock = new THREE.Clock();
      var raycaster = new THREE.Raycaster();
      var mouse = new THREE.Vector2();
      var hoveredMesh = null;
  
      // Spatial positions (golden angle distribution)
      function generatePositions(count) {
          var positions = [];
          for (var i = 0; i < count; i++) {
              var hAngle = (i * 2.618) % (Math.PI * 2);
              var vAngle = (i * 1.618 + Math.PI / 3) % (Math.PI * 2);
              var hRadius = (i % 3) * 1.2;
              var vRadius = ((i + 1) % 4) * 0.8;
              positions.push({
                  x: (Math.sin(hAngle) * hRadius * 8) / 3,
                  y: (Math.cos(vAngle) * vRadius * 8) / 4
              });
          }
          return positions;
      }
  
      function startGallery() {
          var spatialPos = generatePositions(VISIBLE_COUNT);
          var totalImages = imagePaths.length;
          var geometry = new THREE.PlaneGeometry(1, 1, 32, 32);
  
          for (var i = 0; i < VISIBLE_COUNT; i++) {
              var imgIdx = i % totalImages;
              var mat = new THREE.ShaderMaterial({
                  transparent: true,
                  uniforms: {
                      map: { value: textures[imgIdx] },
                      opacity: { value: 1.0 },
                      blurAmount: { value: 0.0 },
                      scrollForce: { value: 0.0 },
                      time: { value: 0.0 },
                      isHovered: { value: 0.0 }
                  },
                  vertexShader: vertexShader,
                  fragmentShader: fragmentShader
              });
  
              var tex = textures[imgIdx];
              var aspect = tex.image ? tex.image.width / tex.image.height : 16 / 10;
              var scaleX = aspect > 1 ? 3.5 * aspect : 3.5;
              var scaleY = aspect > 1 ? 3.5 : 3.5 / aspect;
  
              var mesh = new THREE.Mesh(geometry, mat);
              var z = ((DEPTH_RANGE / VISIBLE_COUNT) * i) % DEPTH_RANGE;
              mesh.position.set(spatialPos[i].x, spatialPos[i].y, z - HALF_RANGE);
              mesh.scale.set(scaleX, scaleY, 1);
  
              scene.add(mesh);
              meshes.push(mesh);
              materials.push(mat);
              planes.push({
                  index: i,
                  z: z,
                  imageIndex: imgIdx,
                  x: spatialPos[i].x,
                  y: spatialPos[i].y
              });
          }
  
          // Event listeners
          canvas.addEventListener('wheel', function(e) {
              e.preventDefault();
              scrollVelocity += e.deltaY * 0.01;
              autoPlay = false;
              lastInteraction = Date.now();
          }, { passive: false });
  
          // Touch support
          var touchStartY = 0;
          canvas.addEventListener('touchstart', function(e) {
              touchStartY = e.touches[0].clientY;
              autoPlay = false;
              lastInteraction = Date.now();
          }, { passive: true });
  
          canvas.addEventListener('touchmove', function(e) {
              var deltaY = touchStartY - e.touches[0].clientY;
              touchStartY = e.touches[0].clientY;
              scrollVelocity += deltaY * 0.03;
              e.preventDefault();
          }, { passive: false });
  
          // Hover detection
          canvas.addEventListener('mousemove', function(e) {
              var rect = canvas.getBoundingClientRect();
              mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
              mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;
          });
  
          // Auto-play resume
          setInterval(function() {
              if (Date.now() - lastInteraction > 3000) autoPlay = true;
          }, 1000);
  
          // Intersection observer - pause when not visible
          var isVisible = true;
          if (typeof IntersectionObserver !== 'undefined') {
              var observer = new IntersectionObserver(function(entries) {
                  isVisible = entries[0].isIntersecting;
              }, { threshold: 0.1 });
              observer.observe(wrapper);
          }
  
          // Animation loop
          function animate() {
              requestAnimationFrame(animate);
              if (!isVisible) return;
  
              var delta = clock.getDelta();
              var time = clock.getElapsedTime();
  
              // Auto-play
              if (autoPlay) scrollVelocity += AUTO_SPEED * delta;
  
              // Damping
              scrollVelocity *= 0.95;
  
              // Raycasting for hover
              raycaster.setFromCamera(mouse, camera);
              var intersects = raycaster.intersectObjects(meshes);
              var newHovered = intersects.length > 0 ? intersects[0].object : null;
  
              if (hoveredMesh !== newHovered) {
                  if (hoveredMesh) {
                      hoveredMesh.material.uniforms.isHovered.value = 0.0;
                  }
                  hoveredMesh = newHovered;
              }
              if (hoveredMesh) {
                  hoveredMesh.material.uniforms.isHovered.value = 1.0;
              }
  
              var imageAdvance = VISIBLE_COUNT % totalImages || totalImages;
  
              // Update planes
              for (var i = 0; i < planes.length; i++) {
                  var plane = planes[i];
                  var newZ = plane.z + scrollVelocity * delta * 10;
                  var wrapsForward = 0, wrapsBackward = 0;
  
                  if (newZ >= DEPTH_RANGE) {
                      wrapsForward = Math.floor(newZ / DEPTH_RANGE);
                      newZ -= DEPTH_RANGE * wrapsForward;
                  } else if (newZ < 0) {
                      wrapsBackward = Math.ceil(-newZ / DEPTH_RANGE);
                      newZ += DEPTH_RANGE * wrapsBackward;
                  }
  
                  if (wrapsForward > 0) {
                      plane.imageIndex = (plane.imageIndex + wrapsForward * imageAdvance) % totalImages;
                  }
                  if (wrapsBackward > 0) {
                      var step = plane.imageIndex - wrapsBackward * imageAdvance;
                      plane.imageIndex = ((step % totalImages) + totalImages) % totalImages;
                  }
  
                  plane.z = ((newZ % DEPTH_RANGE) + DEPTH_RANGE) % DEPTH_RANGE;
  
                  // Update mesh position
                  meshes[i].position.z = plane.z - HALF_RANGE;
  
                  // Update texture
                  materials[i].uniforms.map.value = textures[plane.imageIndex];
  
                  // Opacity calculation
                  var norm = plane.z / DEPTH_RANGE;
                  var opacity = 1;
                  if (norm < FADE_IN_START) opacity = 0;
                  else if (norm < FADE_IN_END) opacity = (norm - FADE_IN_START) / (FADE_IN_END - FADE_IN_START);
                  else if (norm > FADE_OUT_END) opacity = 0;
                  else if (norm > FADE_OUT_START) opacity = 1 - (norm - FADE_OUT_START) / (FADE_OUT_END - FADE_OUT_START);
                  opacity = Math.max(0, Math.min(1, opacity));
  
                  // Blur calculation
                  var blur = 0;
                  if (norm < BLUR_IN_START) blur = MAX_BLUR;
                  else if (norm < BLUR_IN_END) blur = MAX_BLUR * (1 - (norm - BLUR_IN_START) / (BLUR_IN_END - BLUR_IN_START));
                  else if (norm > BLUR_OUT_END) blur = MAX_BLUR;
                  else if (norm > BLUR_OUT_START) blur = MAX_BLUR * ((norm - BLUR_OUT_START) / (BLUR_OUT_END - BLUR_OUT_START));
  
                  // Update uniforms
                  materials[i].uniforms.opacity.value = opacity;
                  materials[i].uniforms.blurAmount.value = blur;
                  materials[i].uniforms.scrollForce.value = scrollVelocity;
                  materials[i].uniforms.time.value = time;
              }
  
              renderer.render(scene, camera);
          }
  
          animate();
      }
  
      // Resize handler
      window.addEventListener('resize', function() {
          var w = canvas.clientWidth;
          var h = canvas.clientHeight;
          camera.aspect = w / h;
          camera.updateProjectionMatrix();
          renderer.setSize(w, h);
      });
  }
  
  CMS.block('visual/galeria3d', function (sec) {
    var canvas = sec.querySelector('.vis-g3d-canvas');
    if (!canvas) return;
    CMS.inView(canvas, function () { CMS.load('three').then(function () { initGallery3D(canvas); }); });
  });
})();
