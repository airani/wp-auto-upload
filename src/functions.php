<?php

if (! function_exists('wp_basename')) {
    /**
     * i18n friendly version of basename()
     *
     * @since 3.1.0
     *
     * @param string $path   A path.
     * @param string $suffix If the filename ends in suffix this will also be cut off.
     * @return string
     */
    function wp_basename( $path, $suffix = '' ) {
        return urldecode( basename( str_replace( array( '%2F', '%5C' ), '/', urlencode( $path ) ), $suffix ) );
    }
}

if (! function_exists('wp_get_image_mime')) {
    /**
     * Returns the real mime type of an image file.
     *
     * This depends on exif_imagetype() or getimagesize() to determine real mime types.
     *
     * @since 4.7.1
     *
     * @param string $file Full path to the file.
     * @return string|false The actual mime type or false if the type cannot be determined.
     */
    function wp_get_image_mime( $file ) {
        /*
         * Use exif_imagetype() to check the mimetype if available or fall back to
         * getimagesize() if exif isn't avaialbe. If either function throws an Exception
         * we assume the file could not be validated.
         */
        try {
            if ( is_callable( 'exif_imagetype' ) ) {
                $imagetype = exif_imagetype( $file );
                $mime      = ( $imagetype ) ? image_type_to_mime_type( $imagetype ) : false;
            } elseif ( function_exists( 'getimagesize' ) ) {
                $imagesize = getimagesize( $file );
                $mime      = ( isset( $imagesize['mime'] ) ) ? $imagesize['mime'] : false;
            } else {
                $mime = false;
            }
        } catch ( Exception $e ) {
            $mime = false;
        }

        return $mime;
    }
}

if (! function_exists('wp_image_editor_supports')) {
    function wp_image_editor_supports($args = array()) {
        return true;
    }
}
