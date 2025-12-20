/**
 * Simplified MapRenderer - Renders a single map image with automatic rotation from 4 corner coordinates
 */
class SimpleMapRenderer {
    constructor(canvasId, mapConfig) {
        this.canvas = document.getElementById(canvasId);
        if (!this.canvas) {
            console.error('Canvas element not found:', canvasId);
            return;
        }

        this.ctx = this.canvas.getContext('2d');
        this.config = mapConfig;
        this.mapImage = null;

        // Pan and zoom state
        this.scale = 1;
        this.offsetX = 0;
        this.offsetY = 0;
        this.isDragging = false;
        this.lastX = 0;
        this.lastY = 0;

        // Touch state
        this.touches = [];
        this.lastTouchDistance = 0;

        // North rotation will be calculated from coordinates
        this.northRotation = 0;

        // Map dimensions in meters
        this.mapWidthMeters = 0;
        this.mapHeightMeters = 0;
        this.pixelsPerMeter = 1;

        // UI Elements
        this.scaleIndicator = null;
        this.compassRose = null;

        // Layers and POIs
        this.layers = mapConfig.layers || [];
        this.layerImages = new Map(); // Map of layerId -> Image object
        this.activeLayers = new Set(); // Set of active layer IDs

        // Interaction state
        this.hoveredPOI = null; // Currently hovered POI
        this.mouseCanvasX = 0; // Mouse position in canvas coordinates
        this.mouseCanvasY = 0;
        this.popupPOI = null; // POI for which popup is shown

        console.log('SimpleMapRenderer initialized:', {
            backgroundImage: mapConfig.backgroundImage,
            corners: {
                upperLeft: mapConfig.coordinatesUpperLeft,
                upperRight: mapConfig.coordinatesUpperRight,
                lowerLeft: mapConfig.coordinatesLowerLeft,
                lowerRight: mapConfig.coordinatesLowerRight
            },
            layersCount: this.layers.length
        });

        // Initialize active layers from config
        this.layers.forEach(layer => {
            if (layer.active) {
                this.activeLayers.add(layer.id);
            }
        });

        this.init();
    }

    /**
     * Parse coordinate string "lat,lng" to object
     */
    parseCoordinates(coordStr) {
        if (!coordStr || coordStr === "0,0") {
            return null;
        }
        const parts = coordStr.split(',').map(s => parseFloat(s.trim()));
        return { lat: parts[0], lng: parts[1] };
    }

    /**
     * Calculate north rotation from the 4 corner coordinates
     */
    calculateNorthRotation() {
        const ul = this.parseCoordinates(this.config.coordinatesUpperLeft);
        const ur = this.parseCoordinates(this.config.coordinatesUpperRight);
        const ll = this.parseCoordinates(this.config.coordinatesLowerLeft);
        const lr = this.parseCoordinates(this.config.coordinatesLowerRight);

        if (!ul || !ur || !ll || !lr) {
            console.warn('Missing corner coordinates, using default north rotation (0°)');
            this.northRotation = 0;
            return;
        }

        console.log('Corner coordinates:', { ul, ur, ll, lr });

        // Calculate the direction of the upper edge (from UL to UR)
        // This tells us how the map is rotated relative to true north

        // For small areas, use simple approximation
        const midLat = (ul.lat + lr.lat) / 2;
        const lngScale = 111320 * Math.cos(midLat * Math.PI / 180); // meters per degree longitude
        const latScale = 111320; // meters per degree latitude

        // Vector from upper-left to upper-right
        const dLng = ur.lng - ul.lng;
        const dLat = ur.lat - ul.lat;

        const dx = dLng * lngScale; // East-west distance in meters
        const dy = dLat * latScale; // North-south distance in meters (positive = northward)

        console.log('Upper edge vector:', {
            dLng: dLng.toFixed(6),
            dLat: dLat.toFixed(6),
            dx_meters: dx.toFixed(2),
            dy_meters: dy.toFixed(2)
        });

        // Calculate the angle of the upper edge
        // atan2(dy, dx) gives us the bearing of the upper edge
        // - 0° = edge points east
        // - 90° = edge points north
        // - 180° = edge points west
        // - -90° = edge points south
        const upperEdgeAngle = Math.atan2(dy, dx);

        // North is 90° counterclockwise from the upper edge direction
        // If upper edge points east (0°), north is at 90° (up on screen)
        // If upper edge points northeast (45°), north is at 135°
        const northAngleRadians = upperEdgeAngle + Math.PI / 2;

        // Convert to degrees for the compass rose
        // Normalize to 0-360 range
        this.northRotation = (northAngleRadians * 180 / Math.PI) % 360;
        if (this.northRotation < 0) this.northRotation += 360;

        console.log('Rotation calculation:', {
            upperEdgeAngle_deg: (upperEdgeAngle * 180 / Math.PI).toFixed(2),
            northRotation_deg: this.northRotation.toFixed(2),
            interpretation: `Upper edge points ${(upperEdgeAngle * 180 / Math.PI).toFixed(1)}° from east, so north is at ${this.northRotation.toFixed(1)}° on compass`
        });

        // Calculate map dimensions for reference
        const widthMeters = Math.sqrt(dx * dx + dy * dy);
        const heightVec = {
            dx: (ll.lng - ul.lng) * lngScale,
            dy: (ll.lat - ul.lat) * latScale
        };
        const heightMeters = Math.sqrt(heightVec.dx * heightVec.dx + heightVec.dy * heightVec.dy);

        // Store dimensions for later use
        this.mapWidthMeters = widthMeters;
        this.mapHeightMeters = heightMeters;

        console.log('Map dimensions:', {
            width_m: widthMeters.toFixed(2),
            height_m: heightMeters.toFixed(2),
            aspectRatio: (widthMeters / heightMeters).toFixed(2)
        });
    }

    /**
     * Initialize the renderer
     */
    async init() {
        // Calculate north rotation from corner coordinates
        this.calculateNorthRotation();

        // Load map image
        await this.loadMapImage();

        // Load layer images
        await this.loadLayerImages();

        // Set canvas size
        this.resizeCanvas();

        // Create UI elements
        this.createUIElements();

        // Set up event listeners
        this.setupEventListeners();

        // Initial render
        this.render();
    }

    /**
     * Load the map background image
     */
    async loadMapImage() {
        return new Promise((resolve, reject) => {
            if (!this.config.backgroundImage) {
                console.warn('No background image specified');
                resolve();
                return;
            }

            const img = new Image();
            img.onload = () => {
                this.mapImage = img;
                console.log('Map image loaded:', {
                    width: img.width,
                    height: img.height,
                    src: this.config.backgroundImage
                });
                resolve();
            };
            img.onerror = () => {
                console.error('Failed to load map image:', this.config.backgroundImage);
                reject();
            };
            img.src = this.config.backgroundImage;
        });
    }

    /**
     * Load all layer images
     */
    async loadLayerImages() {
        const loadPromises = this.layers.map(layer => {
            if (!layer.imageUrl) {
                return Promise.resolve();
            }

            return new Promise((resolve) => {
                const img = new Image();
                img.onload = () => {
                    this.layerImages.set(layer.id, img);
                    console.log('Layer image loaded:', layer.title);
                    resolve();
                };
                img.onerror = () => {
                    console.error('Failed to load layer image:', layer.title);
                    resolve(); // Continue even if layer fails
                };
                img.src = layer.imageUrl;
            });
        });

        await Promise.all(loadPromises);
        console.log(`Loaded ${this.layerImages.size} layer images`);
    }

    /**
     * Convert geographic coordinates to canvas pixel coordinates using bilinear interpolation
     * This properly handles rotated/skewed maps by using all 4 corner coordinates
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     * @returns {{x: number, y: number}} Canvas coordinates (before transform)
     */
    geoToCanvas(lat, lng) {
        // Get all 4 corner coordinates
        const ul = this.parseCoordinates(this.config.coordinatesUpperLeft);
        const ur = this.parseCoordinates(this.config.coordinatesUpperRight);
        const ll = this.parseCoordinates(this.config.coordinatesLowerLeft);
        const lr = this.parseCoordinates(this.config.coordinatesLowerRight);

        if (!ul || !ur || !ll || !lr) {
            console.warn('Missing corner coordinates for geoToCanvas');
            return { x: 0, y: 0 };
        }

        // Use inverse bilinear interpolation to find (u,v) coordinates
        // The geographic quadrilateral is mapped to the unit square [0,1]x[0,1]
        // Where (0,0)=UL, (1,0)=UR, (0,1)=LL, (1,1)=LR
        
        // For a point P(lat, lng), we solve for (u,v) such that:
        // P = (1-v)[(1-u)*UL + u*UR] + v[(1-u)*LL + u*LR]
        
        // Simplified approach for approximate solution:
        // We'll use an iterative method or direct calculation based on the parallelogram properties
        
        // First, try to find u by projecting onto the top and bottom edges
        const topEdgeLng = ur.lng - ul.lng;
        const topEdgeLat = ur.lat - ul.lat;
        const bottomEdgeLng = lr.lng - ll.lng;
        const bottomEdgeLat = lr.lat - ll.lat;
        
        // Vector from UL to point
        const vecLng = lng - ul.lng;
        const vecLat = lat - ul.lat;
        
        // Left edge (from UL to LL)
        const leftEdgeLng = ll.lng - ul.lng;
        const leftEdgeLat = ll.lat - ul.lat;
        
        // For a parallelogram, we can solve this more directly
        // P = UL + u*(UR-UL) + v*(LL-UL)
        // Solving the 2x2 system:
        // vecLng = u*topEdgeLng + v*leftEdgeLng
        // vecLat = u*topEdgeLat + v*leftEdgeLat
        
        const det = topEdgeLng * leftEdgeLat - topEdgeLat * leftEdgeLng;
        
        let u, v;
        if (Math.abs(det) > 0.0000001) {
            // Cramer's rule
            u = (vecLng * leftEdgeLat - vecLat * leftEdgeLng) / det;
            v = (topEdgeLng * vecLat - topEdgeLat * vecLng) / det;
        } else {
            // Degenerate case - fall back to simple ratio
            console.warn('Degenerate quadrilateral, using simple interpolation');
            u = vecLng / (topEdgeLng || 1);
            v = vecLat / (leftEdgeLat || 1);
        }
        
        // Log for debugging
        if (Math.abs(u - 0.5) < 0.1 && Math.abs(v - 0.5) < 0.1) {
            console.log('POI near center:', {
                lat, lng,
                u: u.toFixed(3),
                v: v.toFixed(3),
                corners: { ul, ur, ll, lr }
            });
        }
        
        // Clamp to valid range (with some tolerance for points slightly outside)
        u = Math.max(-0.1, Math.min(1.1, u));
        v = Math.max(-0.1, Math.min(1.1, v));
        
        // Convert from normalized coordinates (0-1) to canvas coordinates (centered at 0,0)
        // Image spans from -renderWidth/2 to +renderWidth/2
        const x = (u - 0.5) * this.renderWidth;
        const y = (v - 0.5) * this.renderHeight;

        return { x, y };
    }

    /**
     * Toggle layer visibility
     */
    toggleLayer(layerId) {
        if (this.activeLayers.has(layerId)) {
            this.activeLayers.delete(layerId);
        } else {
            this.activeLayers.add(layerId);
        }
        this.render();
    }

    /**
     * Resize canvas to fit container
     */
    resizeCanvas() {
        const container = this.canvas.parentElement;
        const containerWidth = container.clientWidth;
        const containerHeight = container.clientHeight;

        // Canvas always fills the container
        this.canvas.width = containerWidth;
        this.canvas.height = containerHeight;

        // Calculate aspect ratio from real-world dimensions
        const mapAspectRatio = this.mapWidthMeters / this.mapHeightMeters;
        const containerAspectRatio = containerWidth / containerHeight;

        // Calculate the size for the rendered image to maintain aspect ratio
        if (containerAspectRatio > mapAspectRatio) {
            // Container is wider - fit to height
            this.renderHeight = containerHeight;
            this.renderWidth = containerHeight * mapAspectRatio;
        } else {
            // Container is taller - fit to width
            this.renderWidth = containerWidth;
            this.renderHeight = containerWidth / mapAspectRatio;
        }

        // Calculate pixels per meter for scale based on render size
        if (this.mapImage) {
            this.pixelsPerMeter = this.renderWidth / this.mapWidthMeters;
        }

        // Center the map in the canvas
        this.offsetX = this.canvas.width / 2;
        this.offsetY = this.canvas.height / 2;

        // Calculate initial scale to fit entire map
        if (this.mapImage) {
            const scaleX = this.canvas.width / this.renderWidth;
            const scaleY = this.canvas.height / this.renderHeight;
            this.scale = Math.min(scaleX, scaleY) * 0.95; // 95% to add small margin
        }

        console.log('Canvas sized:', {
            canvasWidth: this.canvas.width,
            canvasHeight: this.canvas.height,
            renderWidth: this.renderWidth,
            renderHeight: this.renderHeight,
            mapAspectRatio: mapAspectRatio.toFixed(2),
            pixelsPerMeter: this.pixelsPerMeter.toFixed(2),
            initialScale: this.scale.toFixed(2)
        });
    }

    /**
     * Set up event listeners for pan and zoom
     */
    setupEventListeners() {
        // Mouse events
        this.canvas.addEventListener('mousedown', (e) => {
            // Check if clicking on a POI
            if (this.hoveredPOI) {
                this.showPOIPopup(this.hoveredPOI);
                e.preventDefault();
                return;
            }

            this.isDragging = true;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
            this.canvas.style.cursor = 'grabbing';
        });

        this.canvas.addEventListener('mousemove', (e) => {
            const rect = this.canvas.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;

            // Transform mouse position to map coordinates
            this.mouseCanvasX = (mouseX - this.offsetX) / this.scale;
            this.mouseCanvasY = (mouseY - this.offsetY) / this.scale;

            if (this.isDragging) {
                const dx = e.clientX - this.lastX;
                const dy = e.clientY - this.lastY;
                this.offsetX += dx;
                this.offsetY += dy;
                this.lastX = e.clientX;
                this.lastY = e.clientY;
                this.render();
            } else {
                // Check for POI hover
                const prevHovered = this.hoveredPOI;
                this.hoveredPOI = this.getPOIAtPosition(this.mouseCanvasX, this.mouseCanvasY);
                
                if (this.hoveredPOI !== prevHovered) {
                    this.canvas.style.cursor = this.hoveredPOI ? 'pointer' : 'grab';
                    this.render();
                }
            }
        });

        this.canvas.addEventListener('mouseup', () => {
            this.isDragging = false;
            this.canvas.style.cursor = this.hoveredPOI ? 'pointer' : 'grab';
        });

        this.canvas.addEventListener('mouseleave', () => {
            this.isDragging = false;
            this.hoveredPOI = null;
            this.canvas.style.cursor = 'grab';
            this.render();
        });

        // Wheel for zoom
        this.canvas.addEventListener('wheel', (e) => {
            e.preventDefault();
            const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
            const newScale = this.scale * zoomFactor;

            if (newScale >= 0.5 && newScale <= 5) {
                const rect = this.canvas.getBoundingClientRect();
                const mouseX = e.clientX - rect.left;
                const mouseY = e.clientY - rect.top;

                this.offsetX = mouseX - (mouseX - this.offsetX) * zoomFactor;
                this.offsetY = mouseY - (mouseY - this.offsetY) * zoomFactor;
                this.scale = newScale;

                this.render();
            }
        }, { passive: false });

        // Touch events
        this.canvas.addEventListener('touchstart', (e) => {
            e.preventDefault();
            this.touches = Array.from(e.touches);

            if (this.touches.length === 1) {
                this.isDragging = true;
                this.lastX = this.touches[0].clientX;
                this.lastY = this.touches[0].clientY;
            } else if (this.touches.length === 2) {
                this.isDragging = false;
                this.lastTouchDistance = this.getTouchDistance();
            }
        }, { passive: false });

        this.canvas.addEventListener('touchmove', (e) => {
            e.preventDefault();
            this.touches = Array.from(e.touches);

            if (this.touches.length === 1 && this.isDragging) {
                const dx = this.touches[0].clientX - this.lastX;
                const dy = this.touches[0].clientY - this.lastY;
                this.offsetX += dx;
                this.offsetY += dy;
                this.lastX = this.touches[0].clientX;
                this.lastY = this.touches[0].clientY;
                this.render();
            } else if (this.touches.length === 2) {
                const currentDistance = this.getTouchDistance();
                if (this.lastTouchDistance > 0) {
                    const zoomFactor = currentDistance / this.lastTouchDistance;
                    const newScale = this.scale * zoomFactor;

                    if (newScale >= 0.5 && newScale <= 5) {
                        const centerX = (this.touches[0].clientX + this.touches[1].clientX) / 2;
                        const centerY = (this.touches[0].clientY + this.touches[1].clientY) / 2;
                        const rect = this.canvas.getBoundingClientRect();
                        const touchX = centerX - rect.left;
                        const touchY = centerY - rect.top;

                        this.offsetX = touchX - (touchX - this.offsetX) * zoomFactor;
                        this.offsetY = touchY - (touchY - this.offsetY) * zoomFactor;
                        this.scale = newScale;

                        this.render();
                    }
                }
                this.lastTouchDistance = currentDistance;
            }
        }, { passive: false });

        this.canvas.addEventListener('touchend', (e) => {
            e.preventDefault();
            this.touches = Array.from(e.touches);
            if (this.touches.length === 0) {
                this.isDragging = false;
                this.lastTouchDistance = 0;
            } else if (this.touches.length === 1) {
                this.lastX = this.touches[0].clientX;
                this.lastY = this.touches[0].clientY;
            }
        }, { passive: false });

        this.canvas.style.cursor = 'grab';

        // Window resize
        window.addEventListener('resize', () => {
            this.resizeCanvas();
            this.render();
            this.updateScaleIndicator();
        });
    }

    /**
     * Create UI elements (scale indicator and compass rose)
     */
    createUIElements() {
        const container = this.canvas.parentElement;

        // Create scale indicator
        this.scaleIndicator = document.createElement('div');
        this.scaleIndicator.className = 'map-scale-indicator';
        container.appendChild(this.scaleIndicator);

        // Create compass rose
        this.compassRose = document.createElement('div');
        this.compassRose.className = 'map-compass-rose';
        this.compassRose.innerHTML = `
            <svg width="60" height="60" viewBox="0 0 60 60">
                <defs>
                    <g id="north-arrow">
                        <path d="M 30 10 L 24 28 L 30 25 L 36 28 Z" fill="#e74c3c" stroke="#c0392b" stroke-width="1.5"/>
                    </g>
                    <g id="south-arrow">
                        <path d="M 30 50 L 24 32 L 30 35 L 36 32 Z" fill="#fff" stroke="#333" stroke-width="1.5"/>
                    </g>
                </defs>
                <use href="#north-arrow" transform="rotate(${this.northRotation} 30 30)"/>
                <use href="#south-arrow" transform="rotate(${this.northRotation} 30 30)"/>
            </svg>
        `;
        container.appendChild(this.compassRose);
    }

    /**
     * Update scale indicator
     */
    updateScaleIndicator() {
        if (!this.scaleIndicator) return;

        // Calculate real-world distance for 100px at current scale
        const scaleBarWidthPx = 100;
        const realWorldMeters = scaleBarWidthPx / (this.pixelsPerMeter * this.scale);

        // Round to nice number
        let displayValue = realWorldMeters;
        let unit = 'm';

        if (realWorldMeters >= 1000) {
            displayValue = realWorldMeters / 1000;
            unit = 'km';
        }

        // Round to nice numbers
        if (displayValue < 1) {
            displayValue = Math.ceil(displayValue * 10) / 10;
        } else if (displayValue < 10) {
            displayValue = Math.ceil(displayValue);
        } else if (displayValue < 100) {
            displayValue = Math.ceil(displayValue / 5) * 5;
        } else {
            displayValue = Math.ceil(displayValue / 10) * 10;
        }

        this.scaleIndicator.innerHTML = `
            <div class="map-scale-bar" style="width: ${scaleBarWidthPx}px"></div>
            <div class="map-scale-label">${displayValue} ${unit}</div>
        `;
    }

    /**
     * Get distance between two touch points
     */
    getTouchDistance() {
        if (this.touches.length < 2) return 0;
        const dx = this.touches[1].clientX - this.touches[0].clientX;
        const dy = this.touches[1].clientY - this.touches[0].clientY;
        return Math.sqrt(dx * dx + dy * dy);
    }

    /**
     * Reset view to initial state
     */
    resetView() {
        this.scale = 1;
        this.offsetX = this.canvas.width / 2;
        this.offsetY = this.canvas.height / 2;
        this.render();
    }

    /**
     * Render the map
     */
    render() {
        const ctx = this.ctx;

        // Clear canvas
        ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);

        // Background
        ctx.fillStyle = '#f0f0f0';
        ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);

        if (!this.mapImage) {
            ctx.fillStyle = '#999';
            ctx.font = '16px Arial';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText('Karte wird geladen...', this.canvas.width / 2, this.canvas.height / 2);
            return;
        }

        // Save context
        ctx.save();

        // Apply transformations
        ctx.translate(this.offsetX, this.offsetY);
        ctx.scale(this.scale, this.scale);

        // Draw map image centered with correct aspect ratio
        // Use renderWidth/renderHeight which maintain the geographic proportions
        ctx.drawImage(
            this.mapImage,
            -this.renderWidth / 2,
            -this.renderHeight / 2,
            this.renderWidth,
            this.renderHeight
        );

        // Draw active layers
        this.drawLayers(ctx);

        // Draw POIs from active layers
        this.drawPOIs(ctx);

        // Restore context
        ctx.restore();

        // Update scale indicator
        this.updateScaleIndicator();
    }

    /**
     * Draw all active layers
     * Layers always cover the entire map area
     */
    drawLayers(ctx) {
        this.layers.forEach(layer => {
            if (!this.activeLayers.has(layer.id)) {
                return; // Skip inactive layers
            }

            const layerImage = this.layerImages.get(layer.id);
            if (!layerImage) {
                return; // Skip if image not loaded
            }

            // Draw layer over entire map area (same dimensions as base map)
            ctx.drawImage(
                layerImage,
                -this.renderWidth / 2,
                -this.renderHeight / 2,
                this.renderWidth,
                this.renderHeight
            );
        });
    }

    /**
     * Draw POIs from active layers
     */
    drawPOIs(ctx) {
        this.layers.forEach(layer => {
            if (!this.activeLayers.has(layer.id) || !layer.pois) {
                return;
            }

            layer.pois.forEach(poi => {
                if (!poi.active || !poi.position) {
                    return;
                }

                const coords = this.parseCoordinates(poi.position);
                if (!coords) {
                    return;
                }

                const { x, y } = this.geoToCanvas(coords.lat, coords.lng);

                // Draw POI marker
                const markerSize = 20 / this.scale; // Adjust size based on zoom

                ctx.save();

                // Shadow
                ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
                ctx.shadowBlur = 4;
                ctx.shadowOffsetY = 2;

                // Marker pin
                ctx.fillStyle = '#e74c3c';
                ctx.strokeStyle = '#c0392b';
                ctx.lineWidth = 2 / this.scale;

                ctx.beginPath();
                ctx.arc(x, y, markerSize / 2, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();

                // White center
                ctx.fillStyle = 'white';
                ctx.beginPath();
                ctx.arc(x, y, markerSize / 4, 0, Math.PI * 2);
                ctx.fill();

                ctx.restore();

                // Draw label if zoomed in enough OR if hovered
                const isHovered = this.hoveredPOI && 
                                this.hoveredPOI.layerId === layer.id && 
                                this.hoveredPOI.poiId === poi.id;
                
                if ((this.scale > 1.5 || isHovered) && poi.title) {
                    ctx.save();
                    
                    // Measure text for background
                    ctx.font = `${12 / this.scale}px Arial`;
                    const textWidth = ctx.measureText(poi.title).width;
                    const padding = 4 / this.scale;
                    const bgHeight = 20 / this.scale;
                    
                    // Highlight if hovered
                    ctx.fillStyle = isHovered ? 'rgba(231, 76, 60, 0.95)' : 'rgba(0, 0, 0, 0.8)';
                    ctx.fillRect(
                        x + markerSize,
                        y - bgHeight / 2,
                        textWidth + padding * 2,
                        bgHeight
                    );
                    
                    ctx.fillStyle = 'white';
                    ctx.textAlign = 'left';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(poi.title, x + markerSize + padding, y);
                    ctx.restore();
                }
            });
        });
    }

    /**
     * Get POI at given canvas position
     * @param {number} x - Canvas x coordinate (in map space)
     * @param {number} y - Canvas y coordinate (in map space)
     * @returns {object|null} POI data or null
     */
    getPOIAtPosition(x, y) {
        const hitRadius = 15 / this.scale; // Hit detection radius

        for (const layer of this.layers) {
            if (!this.activeLayers.has(layer.id) || !layer.pois) {
                continue;
            }

            for (const poi of layer.pois) {
                if (!poi.active || !poi.position) {
                    continue;
                }

                const coords = this.parseCoordinates(poi.position);
                if (!coords) {
                    continue;
                }

                const poiPos = this.geoToCanvas(coords.lat, coords.lng);
                const dx = x - poiPos.x;
                const dy = y - poiPos.y;
                const distance = Math.sqrt(dx * dx + dy * dy);

                if (distance <= hitRadius) {
                    return {
                        layerId: layer.id,
                        poiId: poi.id,
                        poi: poi
                    };
                }
            }
        }

        return null;
    }

    /**
     * Show popup for POI
     */
    showPOIPopup(poiData) {
        const poi = poiData.poi;

        // Remove existing popup
        const existingPopup = document.querySelector('.map-poi-popup');
        if (existingPopup) {
            existingPopup.remove();
        }

        // Create popup
        const popup = document.createElement('div');
        popup.className = 'map-poi-popup';
        popup.innerHTML = `
            <div class="map-poi-popup__content">
                <button class="map-poi-popup__close" aria-label="Schließen">&times;</button>
                <h3 class="map-poi-popup__title">${poi.title || 'POI'}</h3>
                <div class="map-poi-popup__description">
                    ${poi.description || 'Keine Beschreibung verfügbar.'}
                </div>
            </div>
        `;

        // Add to container
        const container = this.canvas.parentElement.parentElement;
        container.appendChild(popup);

        // Close button handler
        const closeBtn = popup.querySelector('.map-poi-popup__close');
        closeBtn.addEventListener('click', () => {
            popup.remove();
        });

        // Close on escape
        const escapeHandler = (e) => {
            if (e.key === 'Escape') {
                popup.remove();
                document.removeEventListener('keydown', escapeHandler);
            }
        };
        document.addEventListener('keydown', escapeHandler);

        // Close on click outside
        popup.addEventListener('click', (e) => {
            if (e.target === popup) {
                popup.remove();
            }
        });
    }

    /**
     * Draw compass rose showing north direction
     */
    drawCompassRose(ctx) {
        const size = 80;
        const x = this.canvas.width - size - 20;
        const y = 20 + size / 2;

        ctx.save();

        // Draw circle background
        ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.arc(x, y, size / 2, 0, Math.PI * 2);
        ctx.fill();
        ctx.stroke();

        // Rotate to show north (northRotation is in degrees)
        ctx.translate(x, y);
        ctx.rotate((this.northRotation * Math.PI) / 180);

        // Draw north arrow (red)
        ctx.fillStyle = '#e74c3c';
        ctx.strokeStyle = '#c0392b';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(0, -size / 2 + 8);
        ctx.lineTo(-10, -size / 4);
        ctx.lineTo(0, -size / 2 + 14);
        ctx.lineTo(10, -size / 4);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        // Draw south arrow (white)
        ctx.fillStyle = '#fff';
        ctx.strokeStyle = '#333';
        ctx.beginPath();
        ctx.moveTo(0, size / 2 - 8);
        ctx.lineTo(-10, size / 4);
        ctx.lineTo(0, size / 2 - 14);
        ctx.lineTo(10, size / 4);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();

        // Draw cardinal directions
        ctx.fillStyle = '#333';
        ctx.font = 'bold 16px Arial';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';

        // N (rotated correctly)
        ctx.save();
        ctx.rotate(-this.northRotation * Math.PI / 180);
        ctx.fillText('N', 0, -size / 2 + 25);
        ctx.restore();

        // S
        ctx.save();
        ctx.rotate((180 - this.northRotation) * Math.PI / 180);
        ctx.fillText('S', 0, -size / 2 + 25);
        ctx.restore();

        // E
        ctx.save();
        ctx.rotate((90 - this.northRotation) * Math.PI / 180);
        ctx.fillText('E', 0, -size / 2 + 25);
        ctx.restore();

        // W
        ctx.save();
        ctx.rotate((-90 - this.northRotation) * Math.PI / 180);
        ctx.fillText('W', 0, -size / 2 + 25);
        ctx.restore();

        ctx.restore();

        // Debug info
        ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
        ctx.fillRect(x - 40, y + size / 2 + 10, 80, 20);
        ctx.fillStyle = '#333';
        ctx.font = '12px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(`${this.northRotation}°`, x, y + size / 2 + 20);
    }

    /**
     * Draw scale indicator
     */
    drawScale(ctx) {
        const scaleBarWidth = 100;
        const x = 20;
        const y = this.canvas.height - 40;

        // For now, just show scale factor
        const displayScale = `${(this.scale * 100).toFixed(0)}%`;

        // Draw scale bar background
        ctx.fillStyle = 'rgba(255, 255, 255, 0.95)';
        ctx.fillRect(x - 5, y - 25, scaleBarWidth + 10, 35);

        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.strokeRect(x - 5, y - 25, scaleBarWidth + 10, 35);

        // Draw scale bar
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + scaleBarWidth, y);
        ctx.stroke();

        // Tick marks
        ctx.beginPath();
        ctx.moveTo(x, y - 5);
        ctx.lineTo(x, y + 5);
        ctx.moveTo(x + scaleBarWidth, y - 5);
        ctx.lineTo(x + scaleBarWidth, y + 5);
        ctx.stroke();

        // Label
        ctx.fillStyle = '#333';
        ctx.font = '14px Arial';
        ctx.textAlign = 'center';
        ctx.fillText(displayScale, x + scaleBarWidth / 2, y - 12);
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    const mapRenderer = document.querySelector('.map-renderer');

    if (!mapRenderer) {
        console.warn('Map renderer element not found');
        return;
    }

    // Prevent body scrolling when map container is active
    const mapContainer = document.querySelector('.map-container');
    if (mapContainer) {
        document.body.style.overflow = 'hidden';
    }

    // Parse map configuration with all 4 corners
    let layers = [];
    try {
        const layersData = mapRenderer.dataset.layers;
        if (layersData) {
            layers = JSON.parse(layersData);
            console.log('Parsed layers:', layers);
        }
    } catch (e) {
        console.error('Failed to parse layers data:', e);
    }

    const mapConfig = {
        backgroundImage: mapRenderer.dataset.backgroundimage,
        coordinatesUpperLeft: mapRenderer.dataset.coordinatesupperleft,
        coordinatesUpperRight: mapRenderer.dataset.coordinatesupperright,
        coordinatesLowerLeft: mapRenderer.dataset.coordinateslowerleft,
        coordinatesLowerRight: mapRenderer.dataset.coordinateslowerright,
        layers: layers
    };

    console.log('Initializing map with config:', mapConfig);

    // Create renderer
    const renderer = new SimpleMapRenderer('mapCanvas', mapConfig);

    // Store renderer globally
    window.mapRenderer = renderer;

    // Reset view button
    const resetButton = document.getElementById('resetMapView');
    if (resetButton) {
        resetButton.addEventListener('click', () => {
            renderer.resetView();
        });
    }

    // Layer toggle checkboxes
    const layerToggles = document.querySelectorAll('.map-layer-toggle');
    layerToggles.forEach(toggle => {
        toggle.addEventListener('change', (e) => {
            const layerId = parseInt(e.target.dataset.layerId);
            renderer.toggleLayer(layerId);
            console.log('Toggled layer:', layerId, e.target.checked);
        });
    });

    // Sidebar toggle
    const toggleButton = document.getElementById('toggleSidebar');
    const sidebar = document.querySelector('.map-controls');
    const wrapper = document.querySelector('.map-renderer-wrapper');
    if (toggleButton && sidebar && wrapper) {
        toggleButton.addEventListener('click', () => {
            sidebar.classList.toggle('is-hidden');
            wrapper.classList.toggle('sidebar-hidden');

            // Resize canvas when sidebar is toggled
            setTimeout(() => {
                renderer.resizeCanvas();
                renderer.render();
            }, 350); // Wait for animation to complete
        });
    }
});
