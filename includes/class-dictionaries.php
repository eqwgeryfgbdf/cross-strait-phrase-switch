<?php
/**
 * Optional extra dictionaries compiled in wp-admin only.
 *
 * @package CrossStraitPhraseSwitch
 */

namespace CrossStraitPhraseSwitch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ingests JSON/NDJSON phrase tables into a browser script in uploads.
 */
class Dictionaries {
	/**
	 * @return string Absolute uploads directory.
	 */
	public static function uploadDir() {
		$uploadDir = wp_upload_dir();
		if ( ! empty( $uploadDir['error'] ) || empty( $uploadDir['basedir'] ) ) {
			return '';
		}
		return trailingslashit( $uploadDir['basedir'] ) . 'cross-strait-phrase-switch';
	}

	/**
	 * @return string Absolute source JSON directory.
	 */
	public static function sourceDir() {
		$dir = self::uploadDir();
		return $dir === '' ? '' : trailingslashit( $dir ) . 'dicts';
	}

	/**
	 * @return string Compiled JS path.
	 */
	public static function compiledPath() {
		$dir = self::uploadDir();
		return $dir === '' ? '' : trailingslashit( $dir ) . 'user-phrases.js';
	}

	/**
	 * @return string Compiled JS URL, or empty.
	 */
	public static function compiledUrl() {
		$uploadDir = wp_upload_dir();
		$path      = self::compiledPath();
		if ( empty( $uploadDir['baseurl'] ) || $path === '' || ! is_readable( $path ) ) {
			return '';
		}
		return trailingslashit( $uploadDir['baseurl'] ) . 'cross-strait-phrase-switch/user-phrases.js';
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function ensureUploadDir() {
		$base = self::uploadDir();
		$src  = self::sourceDir();
		if ( $base === '' || $src === '' ) {
			return new \WP_Error( 'csps_upload_dir', __( 'WordPress uploads directory is not available.', 'cross-strait-phrase-switch' ) );
		}
		foreach ( array( $base, $src ) as $dir ) {
			if ( is_dir( $dir ) ) {
				continue;
			}
			if ( ! wp_mkdir_p( $dir ) ) {
				return new \WP_Error( 'csps_mkdir', sprintf( __( 'Could not create directory %s.', 'cross-strait-phrase-switch' ), $dir ) );
			}
		}
		$indexPath = trailingslashit( $base ) . 'index.php';
		if ( ! file_exists( $indexPath ) ) {
			file_put_contents( $indexPath, "<?php\n// Silence is golden.\n" );
		}
		return true;
	}

	/**
	 * Copy legacy mu-plugin dictionaries once, if present.
	 *
	 * @return void
	 */
	public static function maybeMigrateLegacyDicts() {
		$sourceDir = self::sourceDir();
		$legacyDir = WP_CONTENT_DIR . '/mu-plugins/locale-phrase-convert/dicts';
		if ( $sourceDir === '' || ! is_dir( $legacyDir ) ) {
			return;
		}
		$existing = glob( trailingslashit( $sourceDir ) . '*.json' );
		if ( is_array( $existing ) && $existing !== array() ) {
			return;
		}
		$files = glob( trailingslashit( $legacyDir ) . '*.json' );
		if ( ! is_array( $files ) ) {
			return;
		}
		foreach ( $files as $filePath ) {
			$dest = trailingslashit( $sourceDir ) . basename( $filePath );
			if ( ! copy( $filePath, $dest ) ) {
				error_log( 'Cross-Strait Phrase Switch: failed to migrate dictionary ' . basename( $filePath ) );
				continue;
			}
			chmod( $dest, 0644 );
		}
	}

	/**
	 * @return array<int, string>
	 */
	public static function listSourceFiles() {
		$dir = self::sourceDir();
		if ( $dir === '' || ! is_dir( $dir ) ) {
			return array();
		}
		$files = glob( trailingslashit( $dir ) . '*.json' );
		if ( ! is_array( $files ) ) {
			return array();
		}
		return array_map( 'basename', $files );
	}

	/**
	 * @param array<string, mixed> $file $_FILES entry.
	 * @return true|\WP_Error
	 */
	public static function storeUpload( $file ) {
		$ensured = self::ensureUploadDir();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}
		if ( ! isset( $file['error'], $file['name'], $file['tmp_name'] ) ) {
			return new \WP_Error( 'csps_bad_upload', __( 'The upload payload is incomplete.', 'cross-strait-phrase-switch' ) );
		}
		if ( (int) $file['error'] !== UPLOAD_ERR_OK ) {
			return new \WP_Error( 'csps_upload_error', sprintf( __( 'Upload failed with PHP error code %d.', 'cross-strait-phrase-switch' ), (int) $file['error'] ) );
		}
		$originalName = sanitize_file_name( (string) $file['name'] );
		if ( strtolower( pathinfo( $originalName, PATHINFO_EXTENSION ) ) !== 'json' ) {
			return new \WP_Error( 'csps_not_json', __( 'Only .json dictionary files are accepted.', 'cross-strait-phrase-switch' ) );
		}
		$dest = trailingslashit( self::sourceDir() ) . $originalName;
		if ( ! move_uploaded_file( (string) $file['tmp_name'], $dest ) ) {
			return new \WP_Error( 'csps_move_failed', __( 'Could not store the uploaded dictionary.', 'cross-strait-phrase-switch' ) );
		}
		chmod( $dest, 0644 );
		return true;
	}

	/**
	 * Compile source JSON into user-phrases.js. Never called on front-end page views.
	 *
	 * @return true|\WP_Error
	 */
	public static function compile() {
		$ensured = self::ensureUploadDir();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}
		$sourceDir = self::sourceDir();
		$outPath   = self::compiledPath();
		$files     = glob( trailingslashit( $sourceDir ) . '*.json' );
		if ( ! is_array( $files ) || $files === array() ) {
			if ( is_readable( $outPath ) ) {
				unlink( $outPath );
			}
			return true;
		}

		$toTaiwan   = array();
		$toMainland = array();
		foreach ( $files as $filePath ) {
			$basename  = basename( $filePath );
			$isLiterary = stripos( $basename, 'literary' ) !== false;
			$minLength  = ( $isLiterary || stripos( $basename, 'synthesis' ) !== false ) ? 3 : 2;
			self::ingestFile( $filePath, $isLiterary, $minLength, $toTaiwan, $toMainland );
		}

		$payload = array(
			'toTaiwan'   => self::pairList( $toTaiwan ),
			'toMainland' => self::pairList( $toMainland ),
		);
		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE );
		if ( ! is_string( $json ) || $json === '' ) {
			$error = function_exists( 'json_last_error_msg' ) ? json_last_error_msg() : 'unknown JSON error';
			return new \WP_Error( 'csps_json', sprintf( __( 'Failed to encode dictionaries: %s', 'cross-strait-phrase-switch' ), $error ) );
		}

		$written = file_put_contents( $outPath, 'window.CSPS_USER_DICTS = ' . $json . ";\n", LOCK_EX );
		if ( $written === false ) {
			return new \WP_Error( 'csps_write', __( 'Failed to write the compiled dictionary script.', 'cross-strait-phrase-switch' ) );
		}
		if ( ! chmod( $outPath, 0644 ) ) {
			error_log( 'Cross-Strait Phrase Switch: compiled dictionary is not world-readable.' );
		}
		return true;
	}

	/**
	 * @param string                $filePath Source file.
	 * @param bool                  $requireSharedHan Literary overlap filter.
	 * @param int                   $minLength Minimum term length.
	 * @param array<string, string> $toTaiwan Taiwan map.
	 * @param array<string, string> $toMainland Mainland map.
	 * @return void
	 */
	private static function ingestFile( $filePath, $requireSharedHan, $minLength, &$toTaiwan, &$toMainland ) {
		$handle = fopen( $filePath, 'rb' );
		if ( $handle === false ) {
			error_log( 'Cross-Strait Phrase Switch: cannot open dictionary ' . $filePath );
			return;
		}

		$isNdjson = true;
		while ( ( $line = fgets( $handle ) ) !== false ) {
			$trim = trim( $line );
			if ( $trim === '' ) {
				continue;
			}
			$decoded = json_decode( $trim, true );
			if ( is_array( $decoded ) && self::hasMoeFields( $decoded ) ) {
				self::addMoeEntry( $decoded, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
			} else {
				$isNdjson = false;
			}
			break;
		}

		if ( $isNdjson ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				$trim = trim( $line );
				if ( $trim === '' ) {
					continue;
				}
				$decoded = json_decode( $trim, true );
				if ( is_array( $decoded ) ) {
					self::addMoeEntry( $decoded, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
				}
			}
			fclose( $handle );
			return;
		}

		fclose( $handle );
		$raw = file_get_contents( $filePath );
		if ( $raw === false ) {
			error_log( 'Cross-Strait Phrase Switch: failed to read dictionary ' . $filePath );
			return;
		}
		$parsed = json_decode( $raw, true );
		if ( ! is_array( $parsed ) ) {
			error_log( 'Cross-Strait Phrase Switch: invalid JSON dictionary ' . $filePath );
			return;
		}
		if ( isset( $parsed['toTaiwan'] ) || isset( $parsed['toMainland'] ) ) {
			self::mergePairMap( isset( $parsed['toTaiwan'] ) ? $parsed['toTaiwan'] : array(), $toTaiwan );
			self::mergePairMap( isset( $parsed['toMainland'] ) ? $parsed['toMainland'] : array(), $toMainland );
			return;
		}
		if ( isset( $parsed[0] ) && is_array( $parsed[0] ) ) {
			foreach ( $parsed as $entry ) {
				if ( is_array( $entry ) ) {
					self::addMoeEntry( $entry, $requireSharedHan, $minLength, $toTaiwan, $toMainland );
				}
			}
		}
	}

	/**
	 * @param array<mixed> $entry Row.
	 * @return bool
	 */
	private static function hasMoeFields( $entry ) {
		return isset( $entry['台灣用語'] ) || isset( $entry['大陸用語'] );
	}

	/**
	 * @param array<mixed>          $entry Row.
	 * @param bool                  $requireSharedHan Shared Han filter.
	 * @param int                   $minLength Minimum length.
	 * @param array<string, string> $toTaiwan Taiwan map.
	 * @param array<string, string> $toMainland Mainland map.
	 * @return void
	 */
	private static function addMoeEntry( $entry, $requireSharedHan, $minLength, &$toTaiwan, &$toMainland ) {
		$mainland = self::cleanTerm( isset( $entry['大陸用語'] ) ? $entry['大陸用語'] : '' );
		$variants = self::taiwanVariants( isset( $entry['台灣用語'] ) ? $entry['台灣用語'] : '' );
		$taiwan   = self::pickTaiwan( $variants, $mainland );
		if ( $taiwan === '' || $mainland === '' || $taiwan === $mainland ) {
			return;
		}
		if ( ! self::shouldKeepPair( $taiwan, $mainland, $requireSharedHan, $minLength ) ) {
			return;
		}
		if ( ! isset( $toTaiwan[ $mainland ] ) ) {
			$toTaiwan[ $mainland ] = $taiwan;
		}
		if ( ! isset( $toTaiwan[ $taiwan ] ) ) {
			$toTaiwan[ $taiwan ] = $taiwan;
		}
		if ( ! isset( $toMainland[ $taiwan ] ) ) {
			$toMainland[ $taiwan ] = $mainland;
		}
		if ( ! isset( $toMainland[ $mainland ] ) ) {
			$toMainland[ $mainland ] = $mainland;
		}
	}

	/**
	 * @param mixed $raw Raw term.
	 * @return string
	 */
	private static function cleanTerm( $raw ) {
		if ( ! is_string( $raw ) ) {
			return '';
		}
		$hadPosTag = strpos( $raw, '【' ) !== false;
		$text      = trim( $raw );
		if ( $text === '' || strcasecmp( $text, 'null' ) === 0 ) {
			return '';
		}
		$utf8 = iconv( 'UTF-8', 'UTF-8//IGNORE', $text );
		if ( is_string( $utf8 ) ) {
			$text = $utf8;
		}
		$text = preg_replace( '/【[^】]*】/u', '', $text );
		$text = preg_replace( '/\[[^\]]*\]/u', '', $text );
		$text = preg_replace( '/（[^）]*）/u', '', $text );
		$text = preg_replace( '/\([^)]*\)/u', '', $text );
		$text = preg_replace( '/｛[^｝]*｝/u', '', $text );
		$text = preg_replace( '/\{[^}]*\}/u', '', $text );
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = str_replace( array( "\u{0084}", "\xC2\xA0", '' ), '', $text );
		$text = preg_replace( '/\s+/u', '', $text );
		if ( ! is_string( $text ) ) {
			return '';
		}
		$text = trim( $text, " \t；;、,，/\\" );
		if ( $hadPosTag && preg_match( '/的$/u', $text ) ) {
			$text = preg_replace( '/的$/u', '', $text );
			if ( ! is_string( $text ) ) {
				return '';
			}
		}
		if ( $text === '' || mb_strlen( $text, 'UTF-8' ) > 24 ) {
			return '';
		}
		if ( preg_match( '/[A-Za-z]{3,}/', $text ) ) {
			return '';
		}
		return $text;
	}

	/**
	 * @param mixed $raw Taiwan field.
	 * @return array<int, string>
	 */
	private static function taiwanVariants( $raw ) {
		$out = array();
		if ( ! is_string( $raw ) || $raw === '' ) {
			return $out;
		}
		$parts = preg_split( '/[；;]/u', $raw );
		if ( ! is_array( $parts ) ) {
			return $out;
		}
		foreach ( $parts as $part ) {
			$term = self::cleanTerm( $part );
			if ( $term !== '' ) {
				$out[] = $term;
			}
		}
		return $out;
	}

	/**
	 * @param array<int, string> $variants Taiwan variants.
	 * @param string             $mainland Mainland term.
	 * @return string
	 */
	private static function pickTaiwan( $variants, $mainland ) {
		if ( $variants === array() || $mainland === '' ) {
			return '';
		}
		$mainlandLen = mb_strlen( $mainland, 'UTF-8' );
		$best        = '';
		$bestLen     = -1;
		foreach ( $variants as $term ) {
			$termLen = mb_strlen( $term, 'UTF-8' );
			if ( abs( $termLen - $mainlandLen ) > 2 ) {
				continue;
			}
			if ( $termLen > $bestLen ) {
				$best    = $term;
				$bestLen = $termLen;
			}
		}
		if ( $best !== '' ) {
			return $best;
		}
		return $variants[0];
	}

	/**
	 * @param string $taiwan Taiwan term.
	 * @param string $mainland Mainland term.
	 * @param bool   $requireSharedHan Literary overlap.
	 * @param int    $minLength Minimum length.
	 * @return bool
	 */
	private static function shouldKeepPair( $taiwan, $mainland, $requireSharedHan, $minLength ) {
		$taiwanLen   = mb_strlen( $taiwan, 'UTF-8' );
		$mainlandLen = mb_strlen( $mainland, 'UTF-8' );
		$minLength   = (int) $minLength;
		if ( $minLength < 2 ) {
			$minLength = 2;
		}
		if ( $taiwanLen < $minLength || $mainlandLen < $minLength ) {
			return false;
		}
		$shorter = min( $taiwanLen, $mainlandLen );
		$longer  = max( $taiwanLen, $mainlandLen );
		if ( $longer / $shorter > 2.0 ) {
			return false;
		}
		if ( abs( $taiwanLen - $mainlandLen ) > 3 ) {
			return false;
		}
		$generic = array(
			'碼'   => true,
			'通道' => true,
			'呼叫' => true,
			'聊天' => true,
			'標題' => true,
			'附件' => true,
			'細胞' => true,
			'橋'   => true,
			'擷取' => true,
			'正文' => true,
			'文本' => true,
			'出口' => true,
			'退出' => true,
			'歌曲' => true,
			'歌調' => true,
			'歌调' => true,
			'生活' => true,
			'生命' => true,
			'密度' => true,
			'緊度' => true,
			'紧度' => true,
		);
		if ( isset( $generic[ $taiwan ] ) || isset( $generic[ $mainland ] ) ) {
			return false;
		}
		if ( strpos( $mainland, '；' ) !== false || strpos( $mainland, '、' ) !== false ) {
			return false;
		}
		if ( $requireSharedHan && self::sharedHanCount( $taiwan, $mainland ) < 1 ) {
			return false;
		}
		return true;
	}

	/**
	 * @param string $left First string.
	 * @param string $right Second string.
	 * @return int
	 */
	private static function sharedHanCount( $left, $right ) {
		$rightChars = preg_split( '//u', $right, -1, PREG_SPLIT_NO_EMPTY );
		$leftChars  = preg_split( '//u', $left, -1, PREG_SPLIT_NO_EMPTY );
		if ( ! is_array( $rightChars ) || ! is_array( $leftChars ) ) {
			return 0;
		}
		$set   = array_flip( $rightChars );
		$count = 0;
		foreach ( $leftChars as $char ) {
			if ( isset( $set[ $char ] ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * @param mixed                 $list Pair list.
	 * @param array<string, string> $map Output map.
	 * @return void
	 */
	private static function mergePairMap( $list, &$map ) {
		if ( ! is_array( $list ) ) {
			return;
		}
		foreach ( $list as $entry ) {
			if ( is_array( $entry ) && isset( $entry[0], $entry[1] ) && is_string( $entry[0] ) && is_string( $entry[1] ) ) {
				if ( ! isset( $map[ $entry[0] ] ) ) {
					$map[ $entry[0] ] = $entry[1];
				}
			}
		}
	}

	/**
	 * @param array<string, string> $map Phrase map.
	 * @return array<int, array{0: string, 1: string}>
	 */
	private static function pairList( $map ) {
		$pairs = array();
		foreach ( $map as $source => $target ) {
			$pairs[] = array( $source, $target );
		}
		usort(
			$pairs,
			static function ( $left, $right ) {
				$lengthDiff = mb_strlen( $right[0], 'UTF-8' ) - mb_strlen( $left[0], 'UTF-8' );
				if ( $lengthDiff !== 0 ) {
					return $lengthDiff;
				}
				return strcmp( $left[0], $right[0] );
			}
		);
		return $pairs;
	}
}
