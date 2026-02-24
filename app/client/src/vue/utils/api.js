import localforage from 'localforage'

// Configure localforage
localforage.config({
  name: 'toteam',
  storeName: 'api_cache',
  description: 'ToTeam API Cache'
})

/**
 * API Base URL
 */
const API_BASE = '/api/v1'

/**
 * Cache duration in milliseconds (default: 5 minutes)
 */
const CACHE_DURATION = 5 * 60 * 1000

/**
 * Make an API request with caching
 * @param {string} endpoint - API endpoint
 * @param {object} options - Fetch options
 * @param {boolean} useCache - Whether to use cache
 * @param {number} cacheDuration - Cache duration in ms
 * @returns {Promise<any>}
 */
export async function apiRequest(endpoint, options = {}, useCache = true, cacheDuration = CACHE_DURATION) {
  const url = `${API_BASE}${endpoint}`
  const cacheKey = `${url}_${JSON.stringify(options)}`
  
  // Check cache first for GET requests
  if (useCache && (!options.method || options.method === 'GET')) {
    const cached = await localforage.getItem(cacheKey)
    if (cached && cached.timestamp && Date.now() - cached.timestamp < cacheDuration) {
      console.log(`[API] Using cached data for ${endpoint}`)
      return cached.data
    }
  }
  
  try {
    // Make the request
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...options.headers
      },
      credentials: 'same-origin'
    })
    
    if (!response.ok) {
      throw new Error(`API Error: ${response.status} ${response.statusText}`)
    }
    
    const data = await response.json()
    
    // Cache successful GET requests
    if (useCache && (!options.method || options.method === 'GET')) {
      await localforage.setItem(cacheKey, {
        data,
        timestamp: Date.now()
      })
    }
    
    return data
  } catch (error) {
    console.error(`[API] Error fetching ${endpoint}:`, error)
    
    // Try to return cached data if available (even if expired)
    if (useCache) {
      const cached = await localforage.getItem(cacheKey)
      if (cached && cached.data) {
        console.warn(`[API] Network error, using stale cache for ${endpoint}`)
        return cached.data
      }
    }
    
    throw error
  }
}

/**
 * GET request
 */
export function apiGet(endpoint, useCache = true, cacheDuration = CACHE_DURATION) {
  return apiRequest(endpoint, { method: 'GET' }, useCache, cacheDuration)
}

/**
 * POST request
 */
export function apiPost(endpoint, data) {
  return apiRequest(endpoint, {
    method: 'POST',
    body: JSON.stringify(data)
  }, false)
}

/**
 * PUT request
 */
export function apiPut(endpoint, data) {
  return apiRequest(endpoint, {
    method: 'PUT',
    body: JSON.stringify(data)
  }, false)
}

/**
 * DELETE request
 */
export function apiDelete(endpoint) {
  return apiRequest(endpoint, {
    method: 'DELETE'
  }, false)
}

/**
 * Clear all cache
 */
export async function clearCache() {
  await localforage.clear()
  console.log('[API] Cache cleared')
}

/**
 * Clear cache for specific endpoint
 */
export async function clearCacheForEndpoint(endpoint) {
  const url = `${API_BASE}${endpoint}`
  const keys = await localforage.keys()
  
  for (const key of keys) {
    if (key.startsWith(url)) {
      await localforage.removeItem(key)
    }
  }
  
  console.log(`[API] Cache cleared for ${endpoint}`)
}
