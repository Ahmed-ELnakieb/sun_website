/**
 * Sun Trading Company - Images API Client
 * JavaScript library for accessing images from the database
 */

class ImagesAPI {
    constructor(baseUrl = '/sun_website/admin/api/images.php') {
        this.baseUrl = baseUrl;
        this.cache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
    }

    /**
     * Fetch data from API with caching
     */
    async fetchWithCache(url) {
        const cacheKey = url;
        const cached = this.cache.get(cacheKey);

        if (cached && Date.now() - cached.timestamp < this.cacheTimeout) {
            return cached.data;
        }

        try {
            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (!data.success) {
                throw new Error(data.error || 'API request failed');
            }

            // Cache the result
            this.cache.set(cacheKey, {
                data: data.data,
                timestamp: Date.now()
            });

            return data.data;
        } catch (error) {
            console.error('ImagesAPI Error:', error);
            throw error;
        }
    }

    /**
     * Get all images with optional filtering
     */
    async getImages(options = {}) {
        const params = new URLSearchParams();
        params.append('action', 'list');

        if (options.category) params.append('category', options.category);
        if (options.limit) params.append('limit', options.limit);
        if (options.offset) params.append('offset', options.offset);

        const url = `${this.baseUrl}?${params.toString()}`;
        return await this.fetchWithCache(url);
    }

    /**
     * Get images grouped by category
     */
    async getImagesByCategory() {
        const url = `${this.baseUrl}?action=by_category`;
        return await this.fetchWithCache(url);
    }

    /**
     * Get available categories
     */
    async getCategories() {
        const url = `${this.baseUrl}?action=categories`;
        return await this.fetchWithCache(url);
    }

    /**
     * Get single image by ID
     */
    async getImage(id) {
        const url = `${this.baseUrl}?action=get&id=${id}`;
        return await this.fetchWithCache(url);
    }

    /**
     * Search images
     */
    async searchImages(query, options = {}) {
        const params = new URLSearchParams();
        params.append('action', 'search');
        params.append('q', query);

        if (options.category) params.append('category', options.category);
        if (options.limit) params.append('limit', options.limit);

        const url = `${this.baseUrl}?${params.toString()}`;
        return await this.fetchWithCache(url);
    }

    /**
     * Find image by filename (useful for replacing hardcoded paths)
     */
    async findImageByFilename(filename) {
        try {
            const result = await this.searchImages(filename);
            return result.find(img => img.filename === filename || img.name === filename);
        } catch (error) {
            console.warn(`Could not find image: ${filename}`, error);
            return null;
        }
    }

    /**
     * Get product images
     */
    async getProductImages() {
        return await this.getImages({ category: 'product' });
    }

    /**
     * Get general/background images
     */
    async getGeneralImages() {
        return await this.getImages({ category: 'general' });
    }

    /**
     * Get content/administration images
     */
    async getContentImages() {
        return await this.getImages({ category: 'content' });
    }

    /**
     * Get logo images
     */
    async getLogoImages() {
        return await this.getImages({ category: 'logo' });
    }

    /**
     * Replace hardcoded image sources with database URLs
     */
    async replaceDynamicImages() {
        try {
            const imagesByCategory = await this.getImagesByCategory();

            // Replace product images
            if (imagesByCategory.product) {
                this.replaceProductImages(imagesByCategory.product);
            }

            // Replace background images
            if (imagesByCategory.general) {
                this.replaceBackgroundImages(imagesByCategory.general);
            }

            // Replace content images
            if (imagesByCategory.content) {
                this.replaceContentImages(imagesByCategory.content);
            }

            // Replace logo
            if (imagesByCategory.logo) {
                this.replaceLogoImages(imagesByCategory.logo);
            }

            console.log('✓ Dynamic images loaded from database');
        } catch (error) {
            console.error('Failed to load dynamic images:', error);
            // Fallback to static images if API fails
        }
    }

    /**
     * Replace product images in the DOM
     */
    replaceProductImages(productImages) {
        // Create a map of filename to URL for easy lookup
        const imageMap = new Map();
        productImages.forEach(img => {
            imageMap.set(img.filename.toLowerCase(), img.url);
            imageMap.set(img.name.toLowerCase(), img.url);
        });

        // Find all product image elements
        const productImgs = document.querySelectorAll('img[src*="/products/"], .product-card img');
        productImgs.forEach(img => {
            const currentSrc = img.src;
            const filename = currentSrc.split('/').pop().toLowerCase();

            if (imageMap.has(filename)) {
                img.src = imageMap.get(filename);
                img.setAttribute('data-dynamic-src', 'true');
            }
        });
    }

    /**
     * Replace background images in CSS
     */
    replaceBackgroundImages(generalImages) {
        const imageMap = new Map();
        generalImages.forEach(img => {
            imageMap.set(img.filename.toLowerCase(), img.url);
            imageMap.set(img.name.toLowerCase(), img.url);
        });

        // Update CSS custom properties for backgrounds
        const root = document.documentElement;

        // Common background images
        const backgroundMappings = {
            'background2.png': '--hero-bg-image',
            'hero-background.jpg': '--hero-bg-image-2',
            'background2.jpg': '--contact-bg-image',
            'background3.jpg': '--contact-bg-image-2'
        };

        Object.entries(backgroundMappings).forEach(([filename, cssVar]) => {
            if (imageMap.has(filename.toLowerCase())) {
                root.style.setProperty(cssVar, `url('${imageMap.get(filename.toLowerCase())}')`);
            }
        });
    }

    /**
     * Replace content/administration images
     */
    replaceContentImages(contentImages) {
        const imageMap = new Map();
        contentImages.forEach(img => {
            imageMap.set(img.filename.toLowerCase(), img.url);
            imageMap.set(img.name.toLowerCase(), img.url);
        });

        // Find administration card backgrounds
        const adminCards = document.querySelectorAll('.admin-card');
        adminCards.forEach(card => {
            const classes = Array.from(card.classList);

            classes.forEach(className => {
                let filename = null;

                switch (className) {
                    case 'admin-card-team':
                        filename = 'administration4.png';
                        break;
                    case 'admin-card-management':
                        filename = 'administration3.png';
                        break;
                    case 'admin-card-ceo':
                        filename = 'administration2.png';
                        break;
                    case 'admin-card-values':
                        filename = 'administration1.png';
                        break;
                }

                if (filename && imageMap.has(filename.toLowerCase())) {
                    card.style.backgroundImage = `url('${imageMap.get(filename.toLowerCase())}')`;
                }
            });
        });
    }

    /**
     * Replace logo images
     */
    replaceLogoImages(logoImages) {
        if (logoImages.length > 0) {
            const logoUrl = logoImages[0].url;

            // Find all logo elements
            const logoElements = document.querySelectorAll('img[src*="logo"], .logo img, [class*="logo"] img');
            logoElements.forEach(img => {
                img.src = logoUrl;
                img.setAttribute('data-dynamic-src', 'true');
            });
        }
    }

    /**
     * Clear cache
     */
    clearCache() {
        this.cache.clear();
    }

    /**
     * Preload critical images
     */
    async preloadCriticalImages() {
        try {
            // Preload categories data
            await this.getCategories();
            await this.getImagesByCategory();

            console.log('✓ Critical images preloaded');
        } catch (error) {
            console.error('Failed to preload images:', error);
        }
    }
}

// Global instance
window.ImagesAPI = new ImagesAPI();

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.ImagesAPI.replaceDynamicImages();
    });
} else {
    // DOM is already ready
    window.ImagesAPI.replaceDynamicImages();
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ImagesAPI;
}