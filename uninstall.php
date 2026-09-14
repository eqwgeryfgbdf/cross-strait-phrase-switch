<?php
/**
 * Remove plugin options and compiled dictionaries on uninstall.
 *
 * @package CrossStraitPhraseSwitch
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'cross_strait_phrase_switch' );

$uploadDir = wp_upload_dir();
if ( ! empty( $uploadDir['error'] ) || empty( $uploadDir['basedir'] ) ) {
	return;
}

$targetDir = trailingslashit( $uploadDir['basedir'] ) . 'cross-strait-phrase-switch';
if ( ! is_dir( $targetDir ) ) {
	return;
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $targetDir, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::CHILD_FIRST
);
foreach ( $iterator as $fileInfo ) {
	$path = $fileInfo->getPathname();
	if ( $fileInfo->isDir() ) {
		rmdir( $path );
	} else {
		unlink( $path );
	}
}
rmdir( $targetDir );
