/**
 * Simplified MapRenderer - Renders a single map image with automatic rotation from 4 corner coordinates
 */
class MapRenderer {
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

        // Edit mode state
        this.isEditMode = mapConfig.editMode || false;
        this.draggedPOI = null; // POI being dragged
        this.isDraggingPOI = false;
        this.dragStartX = 0;
        this.dragStartY = 0;

        console.log('MapRenderer initialized:', {
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
        if (!coordStr) {
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
    /**
     * Generate synthetic corner coordinates from the background image aspect ratio.
     * Used when no real geo-coordinates are set so POI positioning works correctly.
     */
    generateSyntheticCoordinates() {
        const ar = this.mapImage.width / this.mapImage.height;
        const halfW = 0.5;
        const halfH = halfW / ar;

        this.config.coordinatesUpperLeft  = `${halfH},${-halfW}`;
        this.config.coordinatesUpperRight = `${halfH},${halfW}`;
        this.config.coordinatesLowerLeft  = `${-halfH},${-halfW}`;
        this.config.coordinatesLowerRight = `${-halfH},${halfW}`;
    }

    async init() {
        // Load images first so we can derive synthetic coordinates if needed
        await this.loadMapImage();
        await this.loadLayerImages();

        // If no corner coordinates are set, generate them from the image aspect ratio
        const hasCoordinates = this.config.coordinatesUpperLeft
            && this.config.coordinatesUpperRight
            && this.config.coordinatesLowerLeft
            && this.config.coordinatesLowerRight;

        if (!hasCoordinates && this.mapImage) {
            this.generateSyntheticCoordinates();
        }

        this.calculateNorthRotation();

        await new Promise(resolve => requestAnimationFrame(resolve));

        this.resizeCanvas();
        this.createUIElements();
        this.setupEventListeners();
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
                resolve(); // Continue init even if image fails (like loadLayerImages does)
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
     * Add a new POI to a specific layer by index (edit mode)
     */
    addNewPOIToLayer(title, color, layerIndex = 0) {
        if (!this.layers[layerIndex]) return null

        const ul = this.parseCoordinates(this.config.coordinatesUpperLeft)
        const lr = this.parseCoordinates(this.config.coordinatesLowerRight)
        const centerLat = ul && lr ? (ul.lat + lr.lat) / 2 : 0
        const centerLng = ul && lr ? (ul.lng + lr.lng) / 2 : 0

        const poi = {
            id: Date.now(),
            title: title,
            description: '',
            active: true,
            position: `${centerLat},${centerLng}`,
            markerColor: color || this.layers[layerIndex].layerColor || '#e74c3c',
            markerText: title.charAt(0).toUpperCase(),
            isNew: true,
        }

        if (!this.layers[layerIndex].pois) this.layers[layerIndex].pois = []
        this.layers[layerIndex].pois.push(poi)
        this.render()
        return poi
    }

    addNewPOI(title, color) {
        return this.addNewPOIToLayer(title, color, 0)
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

        // Use the ACTUAL image aspect ratio, not the geographic aspect ratio
        // This prevents circles from becoming ovals
        let imageAspectRatio = 1;
        if (this.mapImage) {
            imageAspectRatio = this.mapImage.width / this.mapImage.height;
        }

        const containerAspectRatio = containerWidth / containerHeight;

        // Calculate the size for the rendered image to maintain its natural aspect ratio
        if (containerAspectRatio > imageAspectRatio) {
            // Container is wider - fit to height
            this.renderHeight = containerHeight;
            this.renderWidth = containerHeight * imageAspectRatio;
        } else {
            // Container is taller - fit to width
            this.renderWidth = containerWidth;
            this.renderHeight = containerWidth / imageAspectRatio;
        }

        // Calculate pixels per meter for scale based on render size
        // Use the larger dimension to maintain reasonable scale
        if (this.mapImage && this.mapWidthMeters > 0) {
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
            imageAspectRatio: imageAspectRatio.toFixed(2),
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
                if (this.isEditMode) {
                    // In edit mode, start dragging the POI
                    this.draggedPOI = this.hoveredPOI;
                    this.isDraggingPOI = true;
                    this.dragStartX = this.mouseCanvasX;
                    this.dragStartY = this.mouseCanvasY;
                    this.poiWasDragged = false; // Track if POI was actually moved
                    this.canvas.style.cursor = 'grabbing';
                } else {
                    // In view mode, show popup
                    this.showPOIPopup(this.hoveredPOI);
                }
                e.preventDefault();
                return;
            }

            // Start dragging the map
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

            if (this.isDraggingPOI && this.draggedPOI) {
                // Check if POI position has changed significantly (more than 1 pixel in canvas coords)
                const dx = Math.abs(this.mouseCanvasX - this.dragStartX);
                const dy = Math.abs(this.mouseCanvasY - this.dragStartY);
                if (dx > 1 || dy > 1) {
                    this.poiWasDragged = true;
                }

                // Dragging a POI - update its position
                this.updatePOIPosition(this.draggedPOI, this.mouseCanvasX, this.mouseCanvasY);
                this.render();
            } else if (this.isDragging) {
                // Dragging the map
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
            if (this.isDraggingPOI && this.draggedPOI) {
                // Only show popup if POI was clicked (not dragged)
                if (this.isEditMode && !this.poiWasDragged) {
                    this.showPOIPopup(this.draggedPOI);
                }
            }
            this.isDragging = false;
            this.isDraggingPOI = false;
            this.draggedPOI = null;
            this.poiWasDragged = false;
            this.canvas.style.cursor = this.hoveredPOI ? 'pointer' : 'grab';
        });

        this.canvas.addEventListener('mouseleave', () => {
            this.isDragging = false;
            this.isDraggingPOI = false;
            this.draggedPOI = null;
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

                // Draw POI marker with custom color and text
                const markerSize = 24 / this.scale; // Adjust size based on zoom
                const markerColor = poi.markerColor || '#e74c3c';
                const markerText = poi.markerText || '';

                // Darken the marker color for the border
                const darkerColor = this.darkenColor(markerColor, 0.2);

                ctx.save();

                // Shadow
                ctx.shadowColor = 'rgba(0, 0, 0, 0.3)';
                ctx.shadowBlur = 4 / this.scale;
                ctx.shadowOffsetY = 2 / this.scale;

                // Marker circle
                ctx.fillStyle = markerColor;
                ctx.strokeStyle = darkerColor;
                ctx.lineWidth = 2 / this.scale;

                ctx.beginPath();
                ctx.arc(x, y, markerSize / 2, 0, Math.PI * 2);
                ctx.fill();
                ctx.stroke();

                // Reset shadow for text
                ctx.shadowColor = 'transparent';
                ctx.shadowBlur = 0;
                ctx.shadowOffsetY = 0;

                // Draw text on marker if available
                if (markerText) {
                    // Choose text color based on background brightness
                    const textColor = this.getContrastColor(markerColor);
                    ctx.fillStyle = textColor;
                    ctx.font = `bold ${10 / this.scale}px Arial`;
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText(markerText, x, y);
                }

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

        // Different content for edit mode vs view mode
        let popupContent;

        if (this.isEditMode) {
            // Edit mode - show editable fields
            popupContent = `
                <div class="map-poi-popup__content">
                    <button class="map-poi-popup__close" aria-label="Schließen">&times;</button>
                    <h3 class="map-poi-popup__title">Marker bearbeiten</h3>

                    <div class="poi-edit-form">
                        <div class="poi-edit-field">
                            <label for="poiTitle">Titel</label>
                            <input type="text" id="poiTitle" class="form-control" value="${poi.title || ''}" placeholder="Marker-Titel">
                        </div>

                        <div class="poi-edit-field">
                            <label for="poiMarkerText">Marker-Text (max. 4 Zeichen)</label>
                            <input type="text" id="poiMarkerText" class="form-control" value="${poi.markerText || ''}" maxlength="4" placeholder="z.B. A, 1, AB">
                        </div>

                        <div class="poi-edit-field">
                            <label for="poiDescription">Beschreibung</label>
                            <textarea id="poiDescription" class="form-control" rows="3" placeholder="Beschreibung des Markers">${poi.description || ''}</textarea>
                        </div>

                        <div class="poi-edit-field">
                            <label for="poiCoordinates">Koordinaten (Lat, Lng)</label>
                            <input type="text" id="poiCoordinates" class="form-control" value="${poi.position || ''}" placeholder="47.1234, 8.5678">
                            <small class="form-text">Format: Latitude, Longitude</small>
                        </div>
                    </div>

                    <div class="poi-edit-actions">
                        <button class="btn btn--danger btn--small map-poi-popup__delete" data-poi-id="${poi.id}">Löschen</button>
                        <button class="btn btn--secondary btn--small map-poi-popup__cancel">Abbrechen</button>
                    </div>
                </div>
            `;
        } else {
            // View mode - show read-only
            popupContent = `
                <div class="map-poi-popup__content">
                    <button class="map-poi-popup__close" aria-label="Schließen">&times;</button>
                    <h3 class="map-poi-popup__title">${poi.title || 'POI'}</h3>
                    <div class="map-poi-popup__description">
                        ${poi.description || 'Keine Beschreibung verfügbar.'}
                    </div>
                </div>
            `;
        }

        popup.innerHTML = popupContent;

        // Add to container
        const container = this.canvas.parentElement.parentElement;
        container.appendChild(popup);

        // Close button handler
        const closeBtn = popup.querySelector('.map-poi-popup__close');
        closeBtn.addEventListener('click', () => {
            popup.remove();
        });

        // Edit mode specific handlers
        if (this.isEditMode) {
            // Title input
            const titleInput = popup.querySelector('#poiTitle');
            if (titleInput) {
                titleInput.addEventListener('input', (e) => {
                    poi.title = e.target.value;
                    this.updatePOIInState(poiData.layerId, poi);
                    this.render();
                });
            }

            // Marker text input
            const markerTextInput = popup.querySelector('#poiMarkerText');
            if (markerTextInput) {
                markerTextInput.addEventListener('input', (e) => {
                    poi.markerText = e.target.value.substring(0, 4); // Enforce 4 char limit
                    this.updatePOIInState(poiData.layerId, poi);
                    this.render();
                });
            }

            // Description textarea
            const descriptionInput = popup.querySelector('#poiDescription');
            if (descriptionInput) {
                descriptionInput.addEventListener('input', (e) => {
                    poi.description = e.target.value;
                    this.updatePOIInState(poiData.layerId, poi);
                });
            }

            // Coordinates input
            const coordinatesInput = popup.querySelector('#poiCoordinates');
            if (coordinatesInput) {
                coordinatesInput.addEventListener('change', (e) => {
                    const coords = e.target.value.trim();
                    // Validate format (should be "lat, lng")
                    if (/^-?\d+\.?\d*\s*,\s*-?\d+\.?\d*$/.test(coords)) {
                        poi.position = coords;
                        this.updatePOIInState(poiData.layerId, poi);
                        this.render();
                    } else {
                        alert('Ungültiges Koordinatenformat. Bitte verwenden Sie: Latitude, Longitude (z.B. 47.1234, 8.5678)');
                        coordinatesInput.value = poi.position || '';
                    }
                });
            }

            // Delete button
            const deleteBtn = popup.querySelector('.map-poi-popup__delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', () => {
                    if (confirm(`Möchten Sie den Marker "${poi.title}" wirklich löschen?`)) {
                        this.deletePOI(poiData);
                        popup.remove();
                    }
                });
            }

            // Cancel button
            const cancelBtn = popup.querySelector('.map-poi-popup__cancel');
            if (cancelBtn) {
                cancelBtn.addEventListener('click', () => {
                    popup.remove();
                });
            }
        }

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
     * Update POI in layerState (for saving)
     */
    updatePOIInState(layerId, poi) {
        if (window.layerState && window.layerState.pois) {
            const stateIndex = window.layerState.pois.findIndex(p => p.id === poi.id);
            if (stateIndex !== -1) {
                // Update existing POI in state
                window.layerState.pois[stateIndex] = { ...poi };

                // Update POI in sidebar list
                const poiElement = document.querySelector(`.poi-item[data-poi-id="${poi.id}"]`);
                if (poiElement) {
                    // Update title
                    const titleElement = poiElement.querySelector('.poi-info strong');
                    if (titleElement) {
                        titleElement.textContent = poi.title;
                    }

                    // Update marker text
                    const markerTextElement = poiElement.querySelector('.poi-marker span');
                    if (markerTextElement) {
                        markerTextElement.textContent = poi.markerText;
                    }

                    // Update description
                    const descElement = poiElement.querySelector('.poi-info small');
                    if (poi.description) {
                        if (descElement) {
                            descElement.textContent = poi.description.substring(0, 50);
                        } else {
                            const poiInfo = poiElement.querySelector('.poi-info');
                            const small = document.createElement('small');
                            small.textContent = poi.description.substring(0, 50);
                            poiInfo.appendChild(small);
                        }
                    } else if (descElement) {
                        descElement.remove();
                    }

                    // Update position data attribute
                    poiElement.setAttribute('data-poi-position', poi.position);
                }

                // Notify about the change
                if (window.updateLayerState) {
                    window.updateLayerState({ pois: window.layerState.pois });
                }
            }
        }
    }

    /**
     * Update POI position based on canvas coordinates
     */
    updatePOIPosition(poiData, canvasX, canvasY) {
        // Convert canvas coordinates back to geographic coordinates
        const geoCoords = this.canvasToGeo(canvasX, canvasY);

        // Find the POI in the layers and update its position
        const layer = this.layers.find(l => l.id === poiData.layerId);
        if (layer) {
            const poi = layer.pois.find(p => p.id === poiData.poiId);
            if (poi) {
                poi.position = `${geoCoords.lat},${geoCoords.lng}`;

                // Update sidebar list data-poi-position attribute
                const poiElement = document.querySelector(`.poi-item[data-poi-id="${poi.id}"]`);
                if (poiElement) {
                    poiElement.setAttribute('data-poi-position', poi.position);
                }

                // Notify about the change
                if (window.updateLayerState) {
                    window.updateLayerState({ pois: layer.pois });
                }
            }
        }
    }

    /**
     * Convert canvas coordinates back to geographic coordinates
     */
    canvasToGeo(x, y) {
        // Get all 4 corner coordinates
        const ul = this.parseCoordinates(this.config.coordinatesUpperLeft);
        const ur = this.parseCoordinates(this.config.coordinatesUpperRight);
        const ll = this.parseCoordinates(this.config.coordinatesLowerLeft);
        const lr = this.parseCoordinates(this.config.coordinatesLowerRight);

        if (!ul || !ur || !ll || !lr) {
            return { lat: 0, lng: 0 };
        }

        // Convert from canvas coordinates (centered at 0,0) to normalized (0-1)
        const u = (x / this.renderWidth) + 0.5;
        const v = (y / this.renderHeight) + 0.5;

        // Calculate geographic coordinates using the parallelogram formula
        // P = UL + u*(UR-UL) + v*(LL-UL)
        const lat = ul.lat + u * (ur.lat - ul.lat) + v * (ll.lat - ul.lat);
        const lng = ul.lng + u * (ur.lng - ul.lng) + v * (ll.lng - ul.lng);

        return { lat, lng };
    }

    /**
     * Delete a POI
     */
    deletePOI(poiData) {
        const layer = this.layers.find(l => l.id === poiData.layerId);
        if (layer) {
            const index = layer.pois.findIndex(p => p.id === poiData.poiId);
            if (index !== -1) {
                layer.pois.splice(index, 1);

                // Update layerState if in edit mode
                if (window.layerState && window.layerState.pois) {
                    const stateIndex = window.layerState.pois.findIndex(p => p.id === poiData.poiId);
                    if (stateIndex !== -1) {
                        window.layerState.pois.splice(stateIndex, 1);
                    }
                }

                // Notify about the change
                if (window.updateLayerState) {
                    window.updateLayerState({ pois: layer.pois });
                }

                // Remove POI from sidebar list
                const poiElement = document.querySelector(`.poi-item[data-poi-id="${poiData.poiId}"]`);
                if (poiElement) {
                    poiElement.remove();
                }

                // Show "no POIs" message if list is empty
                const poisList = document.querySelector('.pois-list');
                if (poisList && poisList.children.length === 0) {
                    poisList.remove();
                    const poisContainer = document.querySelector('.map-controls_pois');
                    const addPOIButton = document.getElementById('addPOI');
                    if (poisContainer && addPOIButton) {
                        const emptyMessage = document.createElement('p');
                        emptyMessage.className = 'pois-empty';
                        emptyMessage.textContent = 'Noch keine Marker vorhanden';
                        poisContainer.insertBefore(emptyMessage, addPOIButton);
                    }
                }

                // Re-render to remove the POI from the map
                this.render();
            }
        }
    }

    /**
     * Pan the map to center on a POI's coordinates
     * @param {number} lat - Latitude
     * @param {number} lng - Longitude
     */
    panToPOI(lat, lng) {
        // Convert geo coordinates to canvas coordinates
        const { x, y } = this.geoToCanvas(lat, lng);

        // Zoom to maximum level
        this.scale = 5;

        // Calculate offset needed to center this point at the new zoom level
        // We want the point at canvas center, so offset should be:
        // offsetX = canvasCenter.x - point.x (in render space)
        // offsetY = canvasCenter.y - point.y (in render space)

        const canvasCenterX = this.canvas.width / 2;
        const canvasCenterY = this.canvas.height / 2;

        // The point (x, y) is in render coordinates
        // We need to adjust offset so that this point appears at canvas center
        this.offsetX = canvasCenterX - (x * this.scale);
        this.offsetY = canvasCenterY - (y * this.scale);

        // Re-render with new pan position
        this.render();
    }

    /**
     * Darken a hex color by a given amount
     * @param {string} color - Hex color (e.g., '#ff0000')
     * @param {number} amount - Amount to darken (0-1)
     * @returns {string} Darkened hex color
     */
    darkenColor(color, amount) {
        // Remove # if present
        color = color.replace('#', '');

        // Parse RGB
        let r = parseInt(color.substring(0, 2), 16);
        let g = parseInt(color.substring(2, 4), 16);
        let b = parseInt(color.substring(4, 6), 16);

        // Darken
        r = Math.floor(r * (1 - amount));
        g = Math.floor(g * (1 - amount));
        b = Math.floor(b * (1 - amount));

        // Convert back to hex
        return '#' +
            r.toString(16).padStart(2, '0') +
            g.toString(16).padStart(2, '0') +
            b.toString(16).padStart(2, '0');
    }

    /**
     * Get contrast color (black or white) for a given background color
     * @param {string} bgColor - Hex color (e.g., '#ff0000')
     * @returns {string} 'white' or 'black'
     */
    getContrastColor(bgColor) {
        // Remove # if present
        bgColor = bgColor.replace('#', '');

        // Parse RGB
        const r = parseInt(bgColor.substring(0, 2), 16);
        const g = parseInt(bgColor.substring(2, 4), 16);
        const b = parseInt(bgColor.substring(4, 6), 16);

        // Calculate relative luminance using ITU-R BT.709
        const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;

        // Return black for light backgrounds, white for dark backgrounds
        return luminance > 0.5 ? 'black' : 'white';
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

export default MapRenderer

// Legacy init for non-module usage
if (typeof document !== 'undefined' && !import.meta?.url) {
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
    const isEditMode = mapRenderer.dataset.editMode === 'true';

    try {
        // In edit mode, use single layer data; otherwise use layers array
        const layerData = isEditMode ? mapRenderer.dataset.layer : mapRenderer.dataset.layers;

        if (layerData) {
            if (isEditMode) {
                // Single layer for edit mode
                const layer = JSON.parse(layerData);
                layers = [layer];
                console.log('Edit mode - Parsed single layer:', layer);
            } else {
                // Multiple layers for view mode
                layers = JSON.parse(layerData);
                console.log('View mode - Parsed layers:', layers);
            }
        }
    } catch (e) {
        console.error('Failed to parse layer(s) data:', e);
    }

    const mapConfig = {
        backgroundImage: mapRenderer.dataset.backgroundimage,
        coordinatesUpperLeft: mapRenderer.dataset.coordinatesupperleft,
        coordinatesUpperRight: mapRenderer.dataset.coordinatesupperright,
        coordinatesLowerLeft: mapRenderer.dataset.coordinateslowerleft,
        coordinatesLowerRight: mapRenderer.dataset.coordinateslowerright,
        layers: layers,
        editMode: isEditMode
    };

    console.log('Initializing map with config:', mapConfig);

    // Create renderer
    const renderer = new MapRenderer('mapCanvas', mapConfig);

    // Store renderer globally
    window.mapRenderer = renderer;

    // Set text color for existing POI markers based on contrast
    document.querySelectorAll('.poi-marker').forEach(marker => {
        const bgColor = marker.style.backgroundColor;
        if (bgColor) {
            // Convert rgb(r,g,b) to hex
            const rgb = bgColor.match(/\d+/g);
            if (rgb && rgb.length === 3) {
                const hex = '#' +
                    parseInt(rgb[0]).toString(16).padStart(2, '0') +
                    parseInt(rgb[1]).toString(16).padStart(2, '0') +
                    parseInt(rgb[2]).toString(16).padStart(2, '0');
                const textColor = renderer.getContrastColor(hex);
                marker.style.color = textColor;
            }
        }
    });

    // Apply layer color to header and buttons in edit mode
    if (isEditMode && layers.length > 0) {
        const layerColor = layers[0].layerColor || '#999999';
        const textColor = renderer.getContrastColor(layerColor);

        // Apply to header
        const header = document.querySelector('.map-controls_header');
        if (header) {
            header.style.backgroundColor = layerColor;
            header.style.color = textColor;
        }

        // Apply to back button
        const backButton = document.querySelector('.action_back');
        if (backButton) {
            backButton.style.backgroundColor = layerColor;
            backButton.style.color = textColor;
        }

        // Apply to reset button
        const resetButton = document.querySelector('.action_recenter');
        if (resetButton) {
            resetButton.style.backgroundColor = layerColor;
            const resetIcon = resetButton.querySelector('.resetMapView_button');
            if (resetIcon) {
                resetIcon.style.backgroundColor = textColor;
            }
        }
    }

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

    // Apply layer colors to layer items
    layerToggles.forEach(toggle => {
        const layerId = parseInt(toggle.dataset.layerId);
        const layer = layers.find(l => l.id === layerId);

        if (layer && layer.layerColor) {
            const layerItem = toggle.closest('.map-layer-item');
            if (layerItem) {
                // Set background color
                layerItem.style.backgroundColor = layer.layerColor;

                // Calculate contrast text color
                const textColor = renderer.getContrastColor(layer.layerColor);
                layerItem.style.color = textColor;

                // Update hover color (slightly lighter or darker)
                const isLight = textColor === 'black';
                const hoverColor = isLight ?
                    renderer.darkenColor(layer.layerColor, 0.1) :
                    lightenColor(layer.layerColor, 0.1);

                layerItem.dataset.hoverColor = hoverColor;

                // Add hover effect
                layerItem.addEventListener('mouseenter', function() {
                    this.style.backgroundColor = this.dataset.hoverColor;
                });
                layerItem.addEventListener('mouseleave', function() {
                    this.style.backgroundColor = layer.layerColor;
                });
            }
        }
    });

    // Helper function to lighten a color
    function lightenColor(color, amount) {
        color = color.replace('#', '');
        let r = parseInt(color.substring(0, 2), 16);
        let g = parseInt(color.substring(2, 4), 16);
        let b = parseInt(color.substring(4, 6), 16);

        r = Math.min(255, Math.floor(r + (255 - r) * amount));
        g = Math.min(255, Math.floor(g + (255 - g) * amount));
        b = Math.min(255, Math.floor(b + (255 - b) * amount));

        return '#' +
            r.toString(16).padStart(2, '0') +
            g.toString(16).padStart(2, '0') +
            b.toString(16).padStart(2, '0');
    }

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

    // Edit mode functionality
    if (isEditMode && layers.length > 0) {
        const layer = layers[0]; // Single layer in edit mode
        let layerState = {
            id: layer.id,
            title: layer.title,
            description: layer.description || '',
            layerColor: layer.layerColor || '#999999',
            imageUrl: layer.imageUrl,
            pois: layer.pois || []
        };

        // Track changes to layer state
        window.updateLayerState = function(updates) {
            layerState = { ...layerState, ...updates };
            console.log('Layer state updated:', layerState);
        };

        // Title input
        const titleInput = document.getElementById('layerTitle');
        if (titleInput) {
            titleInput.addEventListener('input', (e) => {
                updateLayerState({ title: e.target.value });
            });
        }

        // Description textarea
        const descriptionInput = document.getElementById('layerDescription');
        if (descriptionInput) {
            descriptionInput.addEventListener('input', (e) => {
                updateLayerState({ description: e.target.value });
            });
        }

        // Color picker
        const colorInput = document.getElementById('layerColor');
        const colorTextInput = document.getElementById('layerColorText');

        if (colorInput && colorTextInput) {
            colorInput.addEventListener('input', (e) => {
                const color = e.target.value;
                colorTextInput.value = color;
                updateLayerState({ layerColor: color });

                // Update all POI marker colors immediately
                layerState.pois.forEach(poi => {
                    poi.markerColor = color;
                });
                renderer.layers[0].pois.forEach(poi => {
                    poi.markerColor = color;
                });

                // Update marker colors in sidebar list
                const textColor = renderer.getContrastColor(color);
                document.querySelectorAll('.poi-marker').forEach(marker => {
                    marker.style.backgroundColor = color;
                    const span = marker.querySelector('span');
                    if (span) {
                        span.style.color = textColor;
                    }
                });

                // Update header and button colors
                const header = document.querySelector('.map-controls_header');
                if (header) {
                    header.style.backgroundColor = color;
                    header.style.color = textColor;
                }

                const backButton = document.querySelector('.action_back');
                if (backButton) {
                    backButton.style.backgroundColor = color;
                    backButton.style.color = textColor;
                }

                const resetButton = document.querySelector('.action_recenter');
                if (resetButton) {
                    resetButton.style.backgroundColor = color;
                    const resetIcon = resetButton.querySelector('.resetMapView_button');
                    if (resetIcon) {
                        resetIcon.style.backgroundColor = textColor;
                    }
                }

                renderer.render();
            });

            colorTextInput.addEventListener('input', (e) => {
                const color = e.target.value;
                if (/^#[0-9A-F]{6}$/i.test(color)) {
                    colorInput.value = color;
                    updateLayerState({ layerColor: color });

                    // Update all POI marker colors immediately
                    layerState.pois.forEach(poi => {
                        poi.markerColor = color;
                    });
                    renderer.layers[0].pois.forEach(poi => {
                        poi.markerColor = color;
                    });

                    // Update marker colors in sidebar list
                    const textColor = renderer.getContrastColor(color);
                    document.querySelectorAll('.poi-marker').forEach(marker => {
                        marker.style.backgroundColor = color;
                        const span = marker.querySelector('span');
                        if (span) {
                            span.style.color = textColor;
                        }
                    });

                    // Update header and button colors
                    const header = document.querySelector('.map-controls_header');
                    if (header) {
                        header.style.backgroundColor = color;
                        header.style.color = textColor;
                    }

                    const backButton = document.querySelector('.action_back');
                    if (backButton) {
                        backButton.style.backgroundColor = color;
                        backButton.style.color = textColor;
                    }

                    const resetButton = document.querySelector('.action_recenter');
                    if (resetButton) {
                        resetButton.style.backgroundColor = color;
                        const resetIcon = resetButton.querySelector('.resetMapView_button');
                        if (resetIcon) {
                            resetIcon.style.backgroundColor = textColor;
                        }
                    }

                    renderer.render();
                }
            });
        }

        // Add POI button
        const addPOIButton = document.getElementById('addPOI');
        if (addPOIButton) {
            addPOIButton.addEventListener('click', () => {
                const title = prompt('Titel des neuen Markers:');
                if (!title) return;

                // Get center of current view
                const centerLat = (parseFloat(mapConfig.coordinatesUpperLeft.split(',')[0]) +
                                  parseFloat(mapConfig.coordinatesLowerRight.split(',')[0])) / 2;
                const centerLng = (parseFloat(mapConfig.coordinatesUpperLeft.split(',')[1]) +
                                  parseFloat(mapConfig.coordinatesLowerRight.split(',')[1])) / 2;

                const newPOI = {
                    id: Date.now(), // Temporary ID
                    title: title,
                    description: '',
                    active: true,
                    position: `${centerLat},${centerLng}`,
                    markerColor: layerState.layerColor || '#e74c3c',
                    markerText: title.charAt(0).toUpperCase(),
                    isNew: true // Flag for backend
                };

                layerState.pois.push(newPOI);
                updateLayerState({ pois: layerState.pois });

                // Add to renderer's layer (not duplicating, using layerState reference)
                renderer.layers[0].pois = layerState.pois;
                renderer.render();

                // Add POI to the sidebar list
                const poisList = document.querySelector('.pois-list');
                const poisEmpty = document.querySelector('.pois-empty');

                if (poisEmpty) {
                    poisEmpty.remove(); // Remove "no POIs" message
                }

                if (!poisList) {
                    // Create list if it doesn't exist
                    const poisContainer = document.querySelector('.map-controls_pois');
                    const newList = document.createElement('div');
                    newList.className = 'pois-list';
                    poisContainer.insertBefore(newList, addPOIButton);
                }

                const list = document.querySelector('.pois-list');
                if (list) {
                    const textColor = renderer.getContrastColor(newPOI.markerColor);
                    const poiElement = document.createElement('div');
                    poiElement.className = 'poi-item';
                    poiElement.setAttribute('data-poi-id', newPOI.id);
                    poiElement.setAttribute('data-poi-position', newPOI.position);
                    poiElement.innerHTML = `
                        <div class="poi-marker" style="background-color: ${newPOI.markerColor}; color: ${textColor};">
                            <span>${newPOI.markerText}</span>
                        </div>
                        <div class="poi-info">
                            <strong>${newPOI.title}</strong>
                            ${newPOI.description ? `<small>${newPOI.description}</small>` : ''}
                        </div>
                        <button class="poi-item_delete" data-poi-id="${newPOI.id}" title="Marker löschen">
                            <div style="mask-image: url('_resources/app/client/icons/actions/action_trash.svg');" class="icon--small"></div>
                        </button>
                    `;
                    list.appendChild(poiElement);

                    // Add event listeners to the new POI item
                    attachPOIItemListeners(poiElement, renderer);
                }

                console.log('POI added:', newPOI);
            });
        }

        // Helper function to attach listeners to POI items
        const attachPOIItemListeners = (poiElement, renderer) => {
            const poiId = poiElement.getAttribute('data-poi-id');

            // Click on POI info to pan and show popup
            const poiInfo = poiElement.querySelector('.poi-info, .poi-marker');
            if (poiInfo) {
                poiInfo.style.cursor = 'pointer';
                poiInfo.addEventListener('click', () => {
                    // Read position on click to get updated coordinates after drag
                    const poiPosition = poiElement.getAttribute('data-poi-position');

                    if (poiPosition && renderer) {
                        const coords = poiPosition.split(',');
                        const lat = parseFloat(coords[0]);
                        const lng = parseFloat(coords[1]);

                        // Find the POI in the layer
                        const layer = renderer.layers[0];
                        const poi = layer.pois.find(p => p.id == poiId);

                        if (poi) {
                            // Pan to POI
                            renderer.panToPOI(lat, lng);

                            // Show popup after a short delay to let pan complete
                            setTimeout(() => {
                                renderer.showPOIPopup({
                                    layerId: layer.id,
                                    poiId: poi.id,
                                    poi: poi
                                });
                            }, 300);
                        }
                    }
                });
            }

            // Delete button
            const deleteBtn = poiElement.querySelector('.poi-item_delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation(); // Prevent triggering poi-info click

                    const layer = renderer.layers[0];
                    const poi = layer.pois.find(p => p.id == poiId);

                    if (poi && confirm(`Möchten Sie den Marker "${poi.title}" wirklich löschen?`)) {
                        renderer.deletePOI({
                            layerId: layer.id,
                            poiId: poi.id
                        });
                    }
                });
            }
        };

        // Attach listeners to existing POI items from template
        document.querySelectorAll('.poi-item').forEach(poiElement => {
            attachPOIItemListeners(poiElement, renderer);
        });

        // Save button functionality with icon states
        const saveButton = document.getElementById('saveLayer');
        const saveIconButton = document.querySelector('.headerSave_button');
        const saveIconSaving = document.querySelector('.headerSave_saving');
        const saveIconSaved = document.querySelector('.headerSave_saved');

        // Helper function to toggle save icon states
        const toggleSaveIcon = (state) => {
            if (!saveIconButton || !saveIconSaving || !saveIconSaved) return;

            // Hide all icons first
            saveIconButton.style.display = 'none';
            saveIconSaving.style.display = 'none';
            saveIconSaved.style.display = 'none';

            // Show the requested icon
            if (state === 'button') {
                saveIconButton.style.display = 'block';
            } else if (state === 'saving') {
                saveIconSaving.style.display = 'block';
            } else if (state === 'saved') {
                saveIconSaved.style.display = 'block';
            }
        };

        if (saveButton) {
            saveButton.addEventListener('click', async () => {
                saveButton.disabled = true;
                toggleSaveIcon('saving');

                try {
                    // First, upload image if one was selected
                    if (layerState.pendingImageFile) {
                        const formData = new FormData();
                        formData.append('image', layerState.pendingImageFile);
                        formData.append('layerID', layer.id);

                        const uploadResponse = await fetch(`/maps/uploadlayerimage/${layer.id}`, {
                            method: 'POST',
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            body: formData
                        });

                        const uploadResult = await uploadResponse.json();

                        if (!uploadResult.success) {
                            throw new Error(uploadResult.error || 'Fehler beim Hochladen des Bildes');
                        }

                        // Update imageUrl in layerState with uploaded image
                        if (uploadResult.imageUrl) {
                            layerState.imageUrl = uploadResult.imageUrl;
                        }

                        // Clear pending file
                        delete layerState.pendingImageFile;
                    }

                    // Then save all other layer changes
                    const response = await fetch(`/maps/savelayer/${layer.id}`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(layerState)
                    });

                    const result = await response.json();

                    if (result.success) {
                        // Show saved icon
                        toggleSaveIcon('saved');

                        // Reset file input
                        if (imageUploadInput) {
                            imageUploadInput.value = '';
                        }

                        // Return to button icon after 4 seconds
                        setTimeout(() => {
                            toggleSaveIcon('button');
                            saveButton.disabled = false;
                        }, 4000);
                    } else {
                        throw new Error(result.error || 'Fehler beim Speichern');
                    }
                } catch (error) {
                    console.error('Save error:', error);
                    alert('Fehler beim Speichern: ' + error.message);
                    toggleSaveIcon('button');
                    saveButton.disabled = false;
                }
            });
        }

// Image upload functionality - Preview only, actual upload on save
        const imageUploadInput = document.getElementById('layerImageUpload');
        if (imageUploadInput) {
            imageUploadInput.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (!file) return;

                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Bitte wählen Sie eine Bilddatei aus.');
                    e.target.value = ''; // Reset input
                    return;
                }

                // Store file in layerState for later upload
                layerState.pendingImageFile = file;

                // Create preview URL
                const previewUrl = URL.createObjectURL(file);

                // Update preview in sidebar
                const preview = document.querySelector('.layer-image-preview');
                if (preview) {
                    preview.innerHTML = `<img src="${previewUrl}" alt="Vorschau">`;
                }

                // Update renderer - load image and render
                const img = new Image();
                img.onload = () => {
                    // Update the layer's image in the renderer's layerImages Map
                    if (renderer && renderer.layers && renderer.layers[0]) {
                        const layerId = renderer.layers[0].id;
                        renderer.layerImages.set(layerId, img);
                        renderer.render();
                        console.log('Preview image loaded and rendered for layer:', layerId);
                    }
                };
                img.onerror = () => {
                    console.error('Error loading preview image');
                    alert('Fehler beim Laden der Bildvorschau');
                };
                img.src = previewUrl;

                console.log('Image preview loaded - remember to save changes');
            });
        }

        // Make layer state accessible globally for future POI editing
        window.layerState = layerState;
    }})
}
