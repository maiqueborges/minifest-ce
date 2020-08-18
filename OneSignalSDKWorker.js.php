importScripts( 'https://cdn.onesignal.com/sdks/OneSignalSDKWorker.js' );
'use strict';

/**
 * Service Worker of Cooperadores do Evangelho
 * To learn more and add one to your website, visit - https://superpwa.com
 */
 
const cacheName = 'www.cooperadoresdoevangelho.blogspot.com-superpwa-2.1.2';
const startPage = 'https://cooperadoresdoevangelho.blogspot.com';
const offlinePage = 'https://cooperadoresdoevangelho.blogspot.com';
const filesToCache = [startPage, offlinePage];
const neverCacheUrls = [/\/wp-admin/,/\/wp-login/,/preview=true/];

// Install
self.addEventListener('install', function(e) {
	console.log('SuperPWA service worker installation');
	e.waitUntil(
		caches.open(cacheName).then(function(cache) {
			console.log('SuperPWA service worker caching dependencies');
			filesToCache.map(function(url) {
				return cache.add(url).catch(function (reason) {
					return console.log('SuperPWA: ' + String(reason) + ' ' + url);
				});
			});
		})
	);
});

// Activate
self.addEventListener('activate', function(e) {
	console.log('SuperPWA service worker activation');
	e.waitUntil(
		caches.keys().then(function(keyList) {
			return Promise.all(keyList.map(function(key) {
				if ( key !== cacheName ) {
					console.log('SuperPWA old cache removed', key);
					return caches.delete(key);
				}
			}));
		})
	);
	return self.clients.claim();
});

// Fetch
self.addEventListener('fetch', function(e) {
	
	// Return if the current request url is in the never cache list
	if ( ! neverCacheUrls.every(checkNeverCacheList, e.request.url) ) {
	  console.log( 'SuperPWA: Current request is excluded from cache.' );
	  return;
	}
	
	// Return if request url protocal isn't http or https
	if ( ! e.request.url.match(/^(http|https):\/\//i) )
		return;
	
	// Return if request url is from an external domain.
	if ( new URL(e.request.url).origin !== location.origin )
		return;
	
	// For POST requests, do not use the cache. Serve offline page if offline.
	if ( e.request.method !== 'GET' ) {
		e.respondWith(
			fetch(e.request).catch( function() {
				return caches.match(offlinePage);
			})
		);
		return;
	}
	
// Revving strategy
	if ( e.request.mode === 'navigate' && navigator.onLine ) {
		e.respondWith(
			fetch(e.request).then(function(response) {
				return caches.open(cacheName).then(function(cache) {
					cache.put(e.request, response.clone());
					return response;
				});  
			})
		);
		return;
	}

	e.respondWith(
		caches.match(e.request).then(function(response) {
			return response || fetch(e.request).then(function(response) {
				return caches.open(cacheName).then(function(cache) {
					cache.put(e.request, response.clone());
					return response;
				});  
			});
		}).catch(function() {
			return caches.match(offlinePage);
		})
	);
});

// Check if current url is in the neverCacheUrls list
function checkNeverCacheList(url) {
	if ( this.match(url) ) {
		return false;
	}
	return true;
}

// OneSignal
function superpwa_onesignal_todo() {
	
	// If OneSignal is installed and active
	if ( class_exists( 'OneSignal' ) ) {
		
		// Filter manifest and service worker for singe websites and not for multisites.
		if ( ! is_multisite() ) {
		
			// Add gcm_sender_id to SuperPWA manifest
			add_filter( 'superpwa_manifest', 'superpwa_onesignal_add_gcm_sender_id' );
			
			// Change service worker filename to match OneSignal's service worker
			add_filter( 'superpwa_sw_filename', 'superpwa_onesignal_sw_filename' );
			
			// Import OneSignal service worker in SuperPWA
			add_filter( 'superpwa_sw_template', 'superpwa_onesignal_sw' );
		}
		
		// Show admin notice.
		add_action( 'admin_notices', 'superpwa_onesignal_admin_notices', 9 );
		add_action( 'network_admin_notices', 'superpwa_onesignal_admin_notices', 9 );
	}
}
add_action( 'plugins_loaded', 'superpwa_onesignal_todo' );

// Add gcm_sender_id to SuperPWA manifest
function superpwa_onesignal_add_gcm_sender_id( $manifest ) {
	
	$manifest['gcm_sender_id'] = '482941778795';
	
	return $manifest;
}

// Change Service Worker filename to OneSignalSDKWorker.js.php
function superpwa_onesignal_sw_filename( $sw_filename ) {
	return 'OneSignalSDKWorker.js.php';
}


// Import OneSignal service worker in SuperPWA
function superpwa_onesignal_sw( $sw ) {
	
	/** 
	 * Checking to see if we are already sending the Content-Type header. 
	 * 
	 * @see superpwa_generate_sw_and_manifest_on_fly()
	 */
	$match = preg_grep( '#Content-Type: text/javascript#i', headers_list() );
	
	if ( ! empty ( $match ) ) {
		
		$onesignal = 'importScripts( \'' . superpwa_httpsify( plugin_dir_url( 'onesignal-free-web-push-notifications/onesignal.php' ) ) . 'sdk_files/OneSignalSDKWorker.js.php\' );' . PHP_EOL;
	
		return $onesignal . $sw;
	}
	
	$onesignal  = '<?php' . PHP_EOL; 
	$onesignal .= 'header( "Content-Type: application/javascript" );' . PHP_EOL;
	$onesignal .= 'echo "importScripts( \'' . superpwa_httpsify( plugin_dir_url( 'onesignal-free-web-push-notifications/onesignal.php' ) ) . 'sdk_files/OneSignalSDKWorker.js.php\' );";' . PHP_EOL;
	$onesignal .= '?>' . PHP_EOL . PHP_EOL;
	
	return $onesignal . $sw;
}

// OneSignal activation todo
function superpwa_onesignal_activation() {
	
	// Do not do anything for multisites
	if ( is_multisite() ) {
		return;
	}
	
	// Filter in gcm_sender_id to SuperPWA manifest
	add_filter( 'superpwa_manifest', 'superpwa_onesignal_add_gcm_sender_id' );
	
	// Regenerate SuperPWA manifest
	superpwa_generate_manifest();
	
	// Delete service worker if it exists
	superpwa_delete_sw();
	
	// Change service worker filename to match OneSignal's service worker
	add_filter( 'superpwa_sw_filename', 'superpwa_onesignal_sw_filename' );
	
	// Import OneSignal service worker in SuperPWA
	add_filter( 'superpwa_sw_template', 'superpwa_onesignal_sw' );
	
	// Regenerate SuperPWA service worker
	superpwa_generate_sw();
}
add_action( 'activate_onesignal-free-web-push-notifications/onesignal.php', 'superpwa_onesignal_activation', 11 );

// OneSignal deactivation todo
function superpwa_onesignal_deactivation() {
	
	// Do not do anything for multisites
	if ( is_multisite() ) {
		return;
	}
	
	// Remove gcm_sender_id from SuperPWA manifest
	remove_filter( 'superpwa_manifest', 'superpwa_onesignal_add_gcm_sender_id' );
	
	// Regenerate SuperPWA manifest
	superpwa_generate_manifest();
	
	// Delete service worker if it exists
	superpwa_delete_sw();
	
	// Restore the default service worker of SuperPWA
	remove_filter( 'superpwa_sw_filename', 'superpwa_onesignal_sw_filename' );
	
	// Remove OneSignal service worker in SuperPWA
	remove_filter( 'superpwa_sw_template', 'superpwa_onesignal_sw' );
	
	// Regenerate SuperPWA service worker
	superpwa_generate_sw();
}
add_action( 'deactivate_onesignal-free-web-push-notifications/onesignal.php', 'superpwa_onesignal_deactivation', 11 );

// Admin notices for OneSignal compatibility
function superpwa_onesignal_admin_notices() {
	
	// Incompatibility notice for Multisites
	if ( is_multisite() && current_user_can( 'manage_options' ) ) {
		
		echo '<div class="notice notice-warning"><p>' . 
		sprintf( 
			__( '<strong>SuperPWA</strong> is not compatible with OneSignal on multisites yet. Disable one of these plugins until the compatibility is available.<br>Please refer to the <a href="%s" target="_blank">OneSignal integration documentation</a> for more info. ', 'super-progressive-web-apps' ), 
			'https://superpwa.com/doc/setup-onesignal-with-superpwa/?utm_source=superpwa-plugin&utm_medium=onesignal-multisite-admin-notice#multisites'
		) . '</p></div>';
		
		// Filter PWA status since PWA is not ready yet. 
		add_filter( 'superpwa_is_pwa_ready', '__return_false' );
	}
}
