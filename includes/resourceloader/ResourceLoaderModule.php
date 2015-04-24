<?php
/**
 * Abstraction for resource loader modules.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @author Trevor Parscal
 * @author Roan Kattouw
 */

/**
 * Abstraction for resource loader modules, with name registration and maxage functionality.
 */
abstract class ResourceLoaderModule {
	# Type of resource
	const TYPE_SCRIPTS = 'scripts';
	const TYPE_STYLES = 'styles';
	const TYPE_MESSAGES = 'messages';
	const TYPE_COMBINED = 'combined';

	# sitewide core module like a skin file or jQuery component
	const ORIGIN_CORE_SITEWIDE = 1;

	# per-user module generated by the software
	const ORIGIN_CORE_INDIVIDUAL = 2;

	# sitewide module generated from user-editable files, like MediaWiki:Common.js, or
	# modules accessible to multiple users, such as those generated by the Gadgets extension.
	const ORIGIN_USER_SITEWIDE = 3;

	# per-user module generated from user-editable files, like User:Me/vector.js
	const ORIGIN_USER_INDIVIDUAL = 4;

	# an access constant; make sure this is kept as the largest number in this group
	const ORIGIN_ALL = 10;

	# script and style modules form a hierarchy of trustworthiness, with core modules like
	# skins and jQuery as most trustworthy, and user scripts as least trustworthy.  We can
	# limit the types of scripts and styles we allow to load on, say, sensitive special
	# pages like Special:UserLogin and Special:Preferences
	protected $origin = self::ORIGIN_CORE_SITEWIDE;

	/* Protected Members */

	protected $name = null;
	protected $targets = array( 'desktop' );

	// In-object cache for file dependencies
	protected $fileDeps = array();
	// In-object cache for message blob mtime
	protected $msgBlobMtime = array();

	/**
	 * @var Config
	 */
	protected $config;

	/* Methods */

	/**
	 * Get this module's name. This is set when the module is registered
	 * with ResourceLoader::register()
	 *
	 * @return string|null Name (string) or null if no name was set
	 */
	public function getName() {
		return $this->name;
	}

	/**
	 * Set this module's name. This is called by ResourceLoader::register()
	 * when registering the module. Other code should not call this.
	 *
	 * @param string $name Name
	 */
	public function setName( $name ) {
		$this->name = $name;
	}

	/**
	 * Get this module's origin. This is set when the module is registered
	 * with ResourceLoader::register()
	 *
	 * @return int ResourceLoaderModule class constant, the subclass default
	 *     if not set manually
	 */
	public function getOrigin() {
		return $this->origin;
	}

	/**
	 * Set this module's origin. This is called by ResourceLoader::register()
	 * when registering the module. Other code should not call this.
	 *
	 * @param int $origin Origin
	 */
	public function setOrigin( $origin ) {
		$this->origin = $origin;
	}

	/**
	 * @param ResourceLoaderContext $context
	 * @return bool
	 */
	public function getFlip( $context ) {
		global $wgContLang;

		return $wgContLang->getDir() !== $context->getDirection();
	}

	/**
	 * Get all JS for this module for a given language and skin.
	 * Includes all relevant JS except loader scripts.
	 *
	 * @param ResourceLoaderContext $context
	 * @return string JavaScript code
	 */
	public function getScript( ResourceLoaderContext $context ) {
		// Stub, override expected
		return '';
	}

	/**
	 * Takes named templates by the module and returns an array mapping.
	 *
	 * @return array of templates mapping template alias to content
	 */
	public function getTemplates() {
		// Stub, override expected.
		return array();
	}

	/**
	 * @return Config
	 * @since 1.24
	 */
	public function getConfig() {
		if ( $this->config === null ) {
			// Ugh, fall back to default
			$this->config = ConfigFactory::getDefaultInstance()->makeConfig( 'main' );
		}

		return $this->config;
	}

	/**
	 * @param Config $config
	 * @since 1.24
	 */
	public function setConfig( Config $config ) {
		$this->config = $config;
	}

	/**
	 * Get the URL or URLs to load for this module's JS in debug mode.
	 * The default behavior is to return a load.php?only=scripts URL for
	 * the module, but file-based modules will want to override this to
	 * load the files directly.
	 *
	 * This function is called only when 1) we're in debug mode, 2) there
	 * is no only= parameter and 3) supportsURLLoading() returns true.
	 * #2 is important to prevent an infinite loop, therefore this function
	 * MUST return either an only= URL or a non-load.php URL.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array Array of URLs
	 */
	public function getScriptURLsForDebug( ResourceLoaderContext $context ) {
		$resourceLoader = $context->getResourceLoader();
		$derivative = new DerivativeResourceLoaderContext( $context );
		$derivative->setModules( array( $this->getName() ) );
		$derivative->setOnly( 'scripts' );
		$derivative->setDebug( true );

		$url = $resourceLoader->createLoaderURL(
			$this->getSource(),
			$derivative
		);

		return array( $url );
	}

	/**
	 * Whether this module supports URL loading. If this function returns false,
	 * getScript() will be used even in cases (debug mode, no only param) where
	 * getScriptURLsForDebug() would normally be used instead.
	 * @return bool
	 */
	public function supportsURLLoading() {
		return true;
	}

	/**
	 * Get all CSS for this module for a given skin.
	 *
	 * @param ResourceLoaderContext $context
	 * @return array List of CSS strings or array of CSS strings keyed by media type.
	 *  like array( 'screen' => '.foo { width: 0 }' );
	 *  or array( 'screen' => array( '.foo { width: 0 }' ) );
	 */
	public function getStyles( ResourceLoaderContext $context ) {
		// Stub, override expected
		return array();
	}

	/**
	 * Get the URL or URLs to load for this module's CSS in debug mode.
	 * The default behavior is to return a load.php?only=styles URL for
	 * the module, but file-based modules will want to override this to
	 * load the files directly. See also getScriptURLsForDebug()
	 *
	 * @param ResourceLoaderContext $context
	 * @return array Array( mediaType => array( URL1, URL2, ... ), ... )
	 */
	public function getStyleURLsForDebug( ResourceLoaderContext $context ) {
		$resourceLoader = $context->getResourceLoader();
		$derivative = new DerivativeResourceLoaderContext( $context );
		$derivative->setModules( array( $this->getName() ) );
		$derivative->setOnly( 'styles' );
		$derivative->setDebug( true );

		$url = $resourceLoader->createLoaderURL(
			$this->getSource(),
			$derivative
		);

		return array( 'all' => array( $url ) );
	}

	/**
	 * Get the messages needed for this module.
	 *
	 * To get a JSON blob with messages, use MessageBlobStore::get()
	 *
	 * @return array List of message keys. Keys may occur more than once
	 */
	public function getMessages() {
		// Stub, override expected
		return array();
	}

	/**
	 * Get the group this module is in.
	 *
	 * @return string Group name
	 */
	public function getGroup() {
		// Stub, override expected
		return null;
	}

	/**
	 * Get the origin of this module. Should only be overridden for foreign modules.
	 *
	 * @return string Origin name, 'local' for local modules
	 */
	public function getSource() {
		// Stub, override expected
		return 'local';
	}

	/**
	 * Where on the HTML page should this module's JS be loaded?
	 *  - 'top': in the "<head>"
	 *  - 'bottom': at the bottom of the "<body>"
	 *
	 * @return string
	 */
	public function getPosition() {
		return 'bottom';
	}

	/**
	 * Whether this module's JS expects to work without the client-side ResourceLoader module.
	 * Returning true from this function will prevent mw.loader.state() call from being
	 * appended to the bottom of the script.
	 *
	 * @return bool
	 */
	public function isRaw() {
		return false;
	}

	/**
	 * Get the loader JS for this module, if set.
	 *
	 * @return mixed JavaScript loader code as a string or boolean false if no custom loader set
	 */
	public function getLoaderScript() {
		// Stub, override expected
		return false;
	}

	/**
	 * Get a list of modules this module depends on.
	 *
	 * Dependency information is taken into account when loading a module
	 * on the client side.
	 *
	 * To add dependencies dynamically on the client side, use a custom
	 * loader script, see getLoaderScript()
	 * @return array List of module names as strings
	 */
	public function getDependencies() {
		// Stub, override expected
		return array();
	}

	/**
	 * Get target(s) for the module, eg ['desktop'] or ['desktop', 'mobile']
	 *
	 * @return array Array of strings
	 */
	public function getTargets() {
		return $this->targets;
	}

	/**
	 * Get the skip function.
	 *
	 * Modules that provide fallback functionality can provide a "skip function". This
	 * function, if provided, will be passed along to the module registry on the client.
	 * When this module is loaded (either directly or as a dependency of another module),
	 * then this function is executed first. If the function returns true, the module will
	 * instantly be considered "ready" without requesting the associated module resources.
	 *
	 * The value returned here must be valid javascript for execution in a private function.
	 * It must not contain the "function () {" and "}" wrapper though.
	 *
	 * @return string|null A JavaScript function body returning a boolean value, or null
	 */
	public function getSkipFunction() {
		return null;
	}

	/**
	 * Get the files this module depends on indirectly for a given skin.
	 * Currently these are only image files referenced by the module's CSS.
	 *
	 * @param string $skin Skin name
	 * @return array List of files
	 */
	public function getFileDependencies( $skin ) {
		// Try in-object cache first
		if ( isset( $this->fileDeps[$skin] ) ) {
			return $this->fileDeps[$skin];
		}

		$dbr = wfGetDB( DB_SLAVE );
		$deps = $dbr->selectField( 'module_deps', 'md_deps', array(
				'md_module' => $this->getName(),
				'md_skin' => $skin,
			), __METHOD__
		);
		if ( !is_null( $deps ) ) {
			$this->fileDeps[$skin] = (array)FormatJson::decode( $deps, true );
		} else {
			$this->fileDeps[$skin] = array();
		}
		return $this->fileDeps[$skin];
	}

	/**
	 * Set preloaded file dependency information. Used so we can load this
	 * information for all modules at once.
	 * @param string $skin Skin name
	 * @param array $deps Array of file names
	 */
	public function setFileDependencies( $skin, $deps ) {
		$this->fileDeps[$skin] = $deps;
	}

	/**
	 * Get the last modification timestamp of the message blob for this
	 * module in a given language.
	 * @param string $lang Language code
	 * @return int UNIX timestamp
	 */
	public function getMsgBlobMtime( $lang ) {
		if ( !isset( $this->msgBlobMtime[$lang] ) ) {
			if ( !count( $this->getMessages() ) ) {
				return 1;
			}

			$dbr = wfGetDB( DB_SLAVE );
			$msgBlobMtime = $dbr->selectField( 'msg_resource', 'mr_timestamp', array(
					'mr_resource' => $this->getName(),
					'mr_lang' => $lang
				), __METHOD__
			);
			// If no blob was found, but the module does have messages, that means we need
			// to regenerate it. Return NOW
			if ( $msgBlobMtime === false ) {
				$msgBlobMtime = wfTimestampNow();
			}
			$this->msgBlobMtime[$lang] = wfTimestamp( TS_UNIX, $msgBlobMtime );
		}
		return $this->msgBlobMtime[$lang];
	}

	/**
	 * Set a preloaded message blob last modification timestamp. Used so we
	 * can load this information for all modules at once.
	 * @param string $lang Language code
	 * @param int $mtime UNIX timestamp
	 */
	public function setMsgBlobMtime( $lang, $mtime ) {
		$this->msgBlobMtime[$lang] = $mtime;
	}

	/* Abstract Methods */

	/**
	 * Get this module's last modification timestamp for a given
	 * combination of language, skin and debug mode flag. This is typically
	 * the highest of each of the relevant components' modification
	 * timestamps. Whenever anything happens that changes the module's
	 * contents for these parameters, the mtime should increase.
	 *
	 * NOTE: The mtime of the module's messages is NOT automatically included.
	 * If you want this to happen, you'll need to call getMsgBlobMtime()
	 * yourself and take its result into consideration.
	 *
	 * NOTE: The mtime of the module's hash is NOT automatically included.
	 * If your module provides a getModifiedHash() method, you'll need to call getHashMtime()
	 * yourself and take its result into consideration.
	 *
	 * @param ResourceLoaderContext $context Context object
	 * @return int UNIX timestamp
	 */
	public function getModifiedTime( ResourceLoaderContext $context ) {
		return 1;
	}

	/**
	 * Helper method for calculating when the module's hash (if it has one) changed.
	 *
	 * @param ResourceLoaderContext $context
	 * @return int UNIX timestamp
	 */
	public function getHashMtime( ResourceLoaderContext $context ) {
		$hash = $this->getModifiedHash( $context );
		if ( !is_string( $hash ) ) {
			return 1;
		}

		// Embed the hash itself in the cache key. This allows for a few nifty things:
		// - During deployment, servers with old and new versions of the code communicating
		//   with the same memcached will not override the same key repeatedly increasing
		//   the timestamp.
		// - In case of the definition changing and then changing back in a short period of time
		//   (e.g. in case of a revert or a corrupt server) the old timestamp and client-side cache
		//   url will be re-used.
		// - If different context-combinations (e.g. same skin, same language or some combination
		//   thereof) result in the same definition, they will use the same hash and timestamp.
		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'resourceloader', 'hashmtime', $this->getName(), $hash );

		$data = $cache->get( $key );
		if ( is_int( $data ) && $data > 0 ) {
			// We've seen this hash before, re-use the timestamp of when we first saw it.
			return $data;
		}

		$timestamp = time();
		$cache->set( $key, $timestamp );
		return $timestamp;
	}

	/**
	 * Get the hash for whatever this module may contain.
	 *
	 * This is the method subclasses should implement if they want to make
	 * use of getHashMTime() inside getModifiedTime().
	 *
	 * @param ResourceLoaderContext $context
	 * @return string|null Hash
	 */
	public function getModifiedHash( ResourceLoaderContext $context ) {
		return null;
	}

	/**
	 * Helper method for calculating when this module's definition summary was last changed.
	 *
	 * @since 1.23
	 *
	 * @param ResourceLoaderContext $context
	 * @return int UNIX timestamp
	 */
	public function getDefinitionMtime( ResourceLoaderContext $context ) {
		$summary = $this->getDefinitionSummary( $context );
		if ( $summary === null ) {
			return 1;
		}

		$hash = md5( json_encode( $summary ) );
		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'resourceloader', 'moduledefinition', $this->getName(), $hash );

		$data = $cache->get( $key );
		if ( is_int( $data ) && $data > 0 ) {
			// We've seen this hash before, re-use the timestamp of when we first saw it.
			return $data;
		}

		wfDebugLog( 'resourceloader', __METHOD__ . ": New definition for module "
			. "{$this->getName()} in context \"{$context->getHash()}\"" );
		// WMF logging for T94810
		global $wgRequest;
		if ( isset( $wgRequest ) && $context->getUser() ) {
			wfDebugLog( 'resourceloader', __METHOD__ . ": Request with user parameter in "
			. "context \"{$context->getHash()}\" from " . $wgRequest->getRequestURL() );
		}

		$timestamp = time();
		$cache->set( $key, $timestamp );
		return $timestamp;
	}

	/**
	 * Get the definition summary for this module.
	 *
	 * This is the method subclasses should implement if they want to make
	 * use of getDefinitionMTime() inside getModifiedTime().
	 *
	 * Return an array containing values from all significant properties of this
	 * module's definition. Be sure to include things that are explicitly ordered,
	 * in their actaul order (bug 37812).
	 *
	 * Avoid including things that are insiginificant (e.g. order of message
	 * keys is insignificant and should be sorted to avoid unnecessary cache
	 * invalidation).
	 *
	 * Avoid including things already considered by other methods inside your
	 * getModifiedTime(), such as file mtime timestamps.
	 *
	 * Serialisation is done using json_encode, which means object state is not
	 * taken into account when building the hash. This data structure must only
	 * contain arrays and scalars as values (avoid object instances) which means
	 * it requires abstraction.
	 *
	 * @since 1.23
	 *
	 * @param ResourceLoaderContext $context
	 * @return array|null
	 */
	public function getDefinitionSummary( ResourceLoaderContext $context ) {
		return array(
			'class' => get_class( $this ),
		);
	}

	/**
	 * Check whether this module is known to be empty. If a child class
	 * has an easy and cheap way to determine that this module is
	 * definitely going to be empty, it should override this method to
	 * return true in that case. Callers may optimize the request for this
	 * module away if this function returns true.
	 * @param ResourceLoaderContext $context
	 * @return bool
	 */
	public function isKnownEmpty( ResourceLoaderContext $context ) {
		return false;
	}

	/** @var JSParser Lazy-initialized; use self::javaScriptParser() */
	private static $jsParser;
	private static $parseCacheVersion = 1;

	/**
	 * Validate a given script file; if valid returns the original source.
	 * If invalid, returns replacement JS source that throws an exception.
	 *
	 * @param string $fileName
	 * @param string $contents
	 * @return string JS with the original, or a replacement error
	 */
	protected function validateScriptFile( $fileName, $contents ) {
		if ( $this->getConfig()->get( 'ResourceLoaderValidateJS' ) ) {
			// Try for cache hit
			// Use CACHE_ANYTHING since filtering is very slow compared to DB queries
			$key = wfMemcKey( 'resourceloader', 'jsparse', self::$parseCacheVersion, md5( $contents ) );
			$cache = wfGetCache( CACHE_ANYTHING );
			$cacheEntry = $cache->get( $key );
			if ( is_string( $cacheEntry ) ) {
				return $cacheEntry;
			}

			$parser = self::javaScriptParser();
			try {
				$parser->parse( $contents, $fileName, 1 );
				$result = $contents;
			} catch ( Exception $e ) {
				// We'll save this to cache to avoid having to validate broken JS over and over...
				$err = $e->getMessage();
				$result = "throw new Error(" . Xml::encodeJsVar( "JavaScript parse error: $err" ) . ");";
			}

			$cache->set( $key, $result );
			return $result;
		} else {
			return $contents;
		}
	}

	/**
	 * @return JSParser
	 */
	protected static function javaScriptParser() {
		if ( !self::$jsParser ) {
			self::$jsParser = new JSParser();
		}
		return self::$jsParser;
	}

	/**
	 * Safe version of filemtime(), which doesn't throw a PHP warning if the file doesn't exist
	 * but returns 1 instead.
	 * @param string $filename File name
	 * @return int UNIX timestamp
	 */
	protected static function safeFilemtime( $filename ) {
		wfSuppressWarnings();
		$mtime = filemtime( $filename ) ?: 1;
		wfRestoreWarnings();

		return $mtime;
	}
}
