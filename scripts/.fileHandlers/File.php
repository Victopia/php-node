<?php
/* Default File upload handler.
 * 
 * Saves file directly and uses "File" as identifier.
 */

$data = file_get_contents( $file['tmp_name'] );

$data = Array(
	NODE_FIELD_COLLECTION => 'File',
	'contents' => "$file[type];" . base64_encode( $data )
);

// Uses existing ID for update.
if ( isset($file['ID']) ) {
	$data['ID'] = $file['ID'];
}

$file = $data;

unset( $data );