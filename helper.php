<?php

namespace seraph_pds;

require_once( ABSPATH . 'wp-includes/pluggable.php' );

function OnAdminApi_GetBindedPostIdByDocGuid( $args )
{
	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( null );

	global $wpdb;

	$fileGuid = @$args[ 'fileGuid' ];

	$postId = $wpdb -> get_var( 'SELECT post_id FROM ' . $wpdb -> postmeta . ' WHERE meta_key=\'_seraph_pds_BindGuid\' AND meta_value=\'' . esc_sql( $fileGuid ) . '\'' );
	return( $postId ? intval( $postId ) : null );
}

function OnAdminApi_UpdatePostInfo( $args )
{
	if( isset( $args[ 'lang' ] ) )
		Wp::SetCurLang( Gen::SanitizeId( $args[ 'lang' ] ) );
	Wp::RemoveLangAttachmentFilters();

	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( $resCheckAccess );

	$data = file_get_contents( 'php://input' );
	if( empty( $data ) )
		return( array( 'status' => 'invaliddata' ) );

	$data = json_decode( $data, true );
	if( !$data )
		return( array( 'status' => 'fail', 'statusDescr' => 'Data JSON parse: ' . json_last_error_msg() ) );

	$post = null;
	$postId = 0;
	{
		if( isset( $args[ 'id' ] ) )
		{
			$postId = intval( $args[ 'id' ] );
			$post = get_post( $postId );
			if( !$post )
				return( array( 'status' => 'notfound' ) );

			if( $post -> post_type != $args[ 'type' ] )
				return( array( 'status' => 'wrongtype' ) );
		}
		else
		{
			if( empty( $data[ 'title' ] ) )
				$data[ 'title' ] = 'New ' . $args[ 'type' ];

			$postId = wp_insert_post( array( 'post_title' => wp_slash( $data[ 'title' ] ), 'post_type' => $args[ 'type' ], 'post_content' => '', 'comment_status' => get_option( 'default_comment_status' ) ) );
			if( !( $postId > 0 ) )
				return( array( 'status' => 'fail' ) );

			unset( $data[ 'title' ] );

			$post = get_post( $postId );
		}
	}

	$arrWarn = array();
	$res = SetPostData( $post, $data, $arrWarn );

	return( array( 'status' => $res[ 'res' ], 'statusDescr' => @$res[ 'descr' ], 'warnings' => $arrWarn, 'data' => array( 'id' => $postId, 'mediaUrl' => Wp::GetMediaUploadUrl( $post, true ), 'postUrl' => get_permalink( $post ), 'postEditUrl' => get_edit_post_link( $post, '' ), 'postSlug' => get_post_field( 'post_name', $post ), 'postTitle' => get_post_field( 'post_title', $post ) ) ) );
}

function OnAdminApi_UpdatePostData( $args )
{
	if( isset( $args[ 'lang' ] ) )
		Wp::SetCurLang( Gen::SanitizeId( $args[ 'lang' ] ) );
	Wp::RemoveLangAttachmentFilters();

	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( $resCheckAccess );

	$postId = intval( $args[ 'id' ] );
	$post = get_post( $postId );
	if( empty( $post ) )
		return( array( 'status' => 'notfound' ) );

	$data = file_get_contents( 'php://input' );
	if( empty( $data ) )
		return( array( 'status' => 'invaliddata' ) );

	$data = json_decode( $data, true );
	if( !$data )
		return( array( 'status' => 'fail', 'statusDescr' => 'Data JSON parse: ' . json_last_error_msg() ) );

	$arrWarn = array();
	$res = SetPostData( $post, $data, $arrWarn );

	return( array( 'status' => $res[ 'res' ], 'statusDescr' => @$res[ 'descr' ], 'warnings' => $arrWarn ) );
}

function OnAdminApi_SanitizeLabel( $args )
{
	return( sanitize_title( $args[ "str" ] ) );
}

function OnAdminApi_GetAttachmentIdFromUrl( $args )
{
	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( false );

	return( Wp::GetAttachmentIdFromUrl( $args[ 'url' ], @$args[ 'lang' ] ) );
}

function OnAdminApi_GetUrl( $args )
{
	$resCheckAccess = _AdminApi_CheckRights();

	if( !@$args[ 'raw' ] )
	{
		if( $resCheckAccess )
			return( array( 'hr' => Gen::E_ACCESS_DENIED, 'descr' => $resCheckAccess[ 'status' ] ) );

		return( getUrl( $args[ 'url' ], !empty( $args[ 'body' ] ) ) );
	}

	if( $resCheckAccess )
	{
		http_response_code( 403 );
		return;
	}

	Plugin::ApiCall_EnableOutput();
	getUrlRaw( $args[ 'url' ] );
}

function OnAdminApi_GetPost( $args )
{
	$slugOrTitlePart = @$args[ 'slugOrTitlePart' ];
	if( empty( $slugOrTitlePart ) )
		return( null );

	$postType = @$args[ 'postType' ];
	if( empty( $postType ) )
		return( null );

	$lang = @$args[ 'lang' ];

	$ids = GetPostsIdsBySlug( $slugOrTitlePart, $postType, 1000 );
	if( !$ids )
		$ids = GetPostsIdsByTitlePart( $slugOrTitlePart, $postType, 1000 );

	if( !$ids )
		return( null );

	if( $lang )
		foreach( $ids as $id )
			if( Wp::GetPostLang( $id, $postType ) == $lang )
				return( array( 'id' => $id ) );

	return( array( 'id' => $ids[ 0 ] ) );
}

function OnAdminApi_UploadImage( $args )
{
	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( $resCheckAccess );

	Wp::RemoveLangAttachmentFilters();

	$data = fopen( 'php://input', 'rb' );

	$resample = @$args[ 'resample' ];
	if( $resample )
		$resample = @json_decode( base64_decode( stripslashes( $resample ) ), true );

	$res = UploadImage( $args[ 'filename' ], $args[ 'contentType' ], $data, @$args[ 'postId' ], !!@$args[ 'addToAttachments' ], !!@$args[ 'overwrite' ], @$args[ 'uploadDir' ], @$args[ 'uploadDirSubDir' ], array( 'title' => @$args[ 'title' ], 'altText' => @$args[ 'altText' ], 'description' => @$args[ 'description' ], 'caption' => @$args[ 'caption' ] ), $resample, @$args[ 'lang' ], !!@$args[ 'delExtWebp' ] );

	$ret = null;
	if( !is_wp_error( $res ) )
	{
		foreach( $res[ 'warnings' ] as &$warning )
			$warning = array( 'status' => $warning -> get_error_code(), 'statusDescr' => $warning -> get_error_message() );

		$ret = array( 'status' => 'ok', 'data' => $res );
	}
	else
		$ret = array( 'status' => $res -> get_error_code(), 'statusDescr' => $res -> get_error_message() );

	fclose( $data );
	return( $ret );
}

function OnAdminApi_UpdatePostTypeTaxonomies( $args )
{
	$resCheckAccess = _AdminApi_CheckRights();
	if( $resCheckAccess )
		return( null );

	$data = file_get_contents( 'php://input' );
	if( empty( $data ) )
		return( null );

	$data = json_decode( $data, true );
	if( !is_array( $data ) )
		return( null );

	$lang = @$args[ 'lang' ];

	$res = Wp::UpdatePostTypeTaxonomies( $data, $args[ 'type' ], $args[ 'postType' ], $lang );
	return( $res );
}

function _AdminApi_CheckRights()
{
	if( current_user_can( 'publish_posts' ) )
		return( null );
	return( array( 'status' => 'accessdenied' ) );
}

function SetPostData( $post, $data, &$arrWarn )
{
	$rmtCfg = PluginRmtCfg::Get();
	$availablePlugins = Plugin::GetAvailablePlugins();

	$slug = @$data[ 'slug' ];

	$langDef = Wp::GetDefLang();

	$argsUpdatePost = array();
	{

		{
			$title = @$data[ 'title' ];
			if( $title )
				$argsUpdatePost[ 'post_title' ] = $title;
		}

		{
			if( $slug !== null )
				$argsUpdatePost[ 'post_name' ] = $slug;
		}

		{
			$excerpt = @$data[ 'excerpt' ];
			if( $excerpt !== null )
				$argsUpdatePost[ 'post_excerpt' ] = $excerpt;
		}

		{
			$date = @$data[ 'date' ];
			if( !empty( $date ) )
			{
				$argsUpdatePost[ 'edit_date' ] = true;
				$argsUpdatePost[ 'post_date' ] = get_date_from_gmt( $date );
				$argsUpdatePost[ 'post_date_gmt' ] = $date;
			}
		}

		{
			$parent = @$data[ 'parent' ];
			if( $parent )
				$argsUpdatePost[ 'post_parent' ] = $parent;
		}

		{
			$order = @$data[ 'order' ];
			if( $order )
				$argsUpdatePost[ 'menu_order' ] = $order;
		}

		{
			$text = @$data[ 'text' ];
			if( $text !== null )
				$argsUpdatePost[ 'post_content' ] = $text;
		}

		{
			$status = @$data[ 'status' ];
			if( !empty( $status ) )
				$argsUpdatePost[ 'post_status' ] = $status;
		}
	}

	{
		$categories = @$data[ 'categories' ];
		if( $categories !== null )
		{
			$applied = false;

			$postTaxonomy = Gen::GetArrField( Wp::GetPostsTaxonomiesByClass( Wp::POST_TAXONOMY_CLASS_CATEGORIES ), array( $post -> post_type, 0 ) );
			if( !empty( $postTaxonomy ) )
			{

				wp_set_post_terms( $post -> ID, $categories, $postTaxonomy );

				$applied = true;
			}

			if( !$applied )
				$arrWarn[] = esc_html_x( 'CatsNotSet', 'admin.Msg', 'seraphinite-post-docx-source' );
		}
	}

	{
		$keywords = @$data[ 'keywords' ];
		if( $keywords !== null )
		{
			$applied = false;

			$postTaxonomy = Gen::GetArrField( Wp::GetPostsTaxonomiesByClass( Wp::POST_TAXONOMY_CLASS_TAGS ), array( $post -> post_type, 0 ) );
			if( !empty( $postTaxonomy ) )
			{

				wp_set_post_terms( $post -> ID, $keywords, $postTaxonomy );

				$applied = true;
			}

			if( !$applied )
				$arrWarn[] = esc_html_x( 'KeywordsNotSet', 'admin.Msg', 'seraphinite-post-docx-source' );
		}
	}

	{
		$featuredImage = @$data[ 'featuredImage' ];
		if( $featuredImage !== null )
		{
			if( !$featuredImage )
			{
				$thumbnailId = get_post_thumbnail_id( $post );
				if( $thumbnailId )
				{
					delete_post_thumbnail( $post );

					$updRes = wp_update_post( array( 'ID' => $thumbnailId, 'post_parent' => 0 ), true );
					if( is_wp_error( $updRes ) )
						$arrWarn[] = sprintf( esc_html_x( 'FeaturImgNotUpdated_%1$s', 'admin.Msg', 'seraphinite-post-docx-source' ), $updRes -> get_error_message() );
				}
			}
			else
			{

				set_post_thumbnail( $post, $featuredImage );

				$updRes = wp_update_post( array( 'ID' => $featuredImage, 'post_parent' => $post -> ID ), true );
				if( is_wp_error( $updRes ) )
					$arrWarn[] = sprintf( esc_html_x( 'FeaturImgNotSet_%1$s', 'admin.Msg', 'seraphinite-post-docx-source' ), $updRes -> get_error_message() );

			}
		}
	}

	{
		$images = @$data[ 'wooProductGalleryImages' ];
		if( $images !== null )
			update_post_meta( $post -> ID, '_product_image_gallery', implode( ',', $images ) );
	}

	{
		$titleSeo = @$data[ 'titleSeo' ];
		if( $titleSeo !== null )
		{
			$bSet = false;

			if( in_array( 'all-in-one-seo-pack', $availablePlugins ) )
			{
				if( empty( $titleSeo ) )
					delete_post_meta( $post -> ID, '_aioseop_title' );
				else
					update_post_meta( $post -> ID, '_aioseop_title', $titleSeo );
				$bSet = true;
			}

			if( in_array( 'seo-by-rank-math', $availablePlugins ) )
			{
				if( empty( $titleSeo ) )
					delete_post_meta( $post -> ID, 'rank_math_title' );
				else
					update_post_meta( $post -> ID, 'rank_math_title', $titleSeo );
				$bSet = true;
			}

			if( in_array( 'wp-seopress', $availablePlugins ) )
			{
				if( empty( $titleSeo ) )
					delete_post_meta( $post -> ID, '_seopress_titles_title' );
				else
					update_post_meta( $post -> ID, '_seopress_titles_title', $titleSeo );
				$bSet = true;
			}

			if( in_array( 'wordpress-seo', $availablePlugins ) )
			{
				if( empty( $titleSeo ) )
					delete_post_meta( $post -> ID, '_yoast_wpseo_title' );
				else
					update_post_meta( $post -> ID, '_yoast_wpseo_title', $titleSeo );
				$bSet = true;
			}

			if( !$bSet )
				$arrWarn[] = vsprintf( esc_html_x( 'TitleSeoNotSet_%1$s%2$s', 'admin.Msg', 'seraphinite-post-docx-source' ), Ui::Link( array( '', '' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SupportedPlugins' ), true ) );
		}
	}

	{
		$descrSeo = @$data[ 'descrSeo' ];
		if( $descrSeo !== null )
		{
			$bSet = false;

			if( in_array( 'all-in-one-seo-pack', $availablePlugins ) )
			{
				if( empty( $descrSeo ) )
					delete_post_meta( $post -> ID, '_aioseop_description' );
				else
					update_post_meta( $post -> ID, '_aioseop_description', $descrSeo );
				$bSet = true;
			}

			if( in_array( 'seo-by-rank-math', $availablePlugins ) )
			{
				if( empty( $descrSeo ) )
					delete_post_meta( $post -> ID, 'rank_math_description' );
				else
					update_post_meta( $post -> ID, 'rank_math_description', $descrSeo );
				$bSet = true;
			}

			if( in_array( 'wp-seopress', $availablePlugins ) )
			{
				if( empty( $descrSeo ) )
					delete_post_meta( $post -> ID, '_seopress_titles_desc' );
				else
					update_post_meta( $post -> ID, '_seopress_titles_desc', $descrSeo );
				$bSet = true;
			}

			if( in_array( 'wordpress-seo', $availablePlugins ) )
			{
				if( empty( $descrSeo ) )
					delete_post_meta( $post -> ID, '_yoast_wpseo_metadesc' );
				else
					update_post_meta( $post -> ID, '_yoast_wpseo_metadesc', $descrSeo );
				$bSet = true;
			}

			if( !$bSet )
				$arrWarn[] = vsprintf( esc_html_x( 'DescrSeoNotSet_%1$s%2$s', 'admin.Msg', 'seraphinite-post-docx-source' ), Ui::Link( array( '', '' ), Plugin::RmtCfgFld_GetLoc( $rmtCfg, 'Help.SupportedPlugins' ), true ) );
		}
	}

	{
		$lang = @$data[ 'lang' ];
		if( $lang !== null )
		{
			$langsAvail = Wp::GetLangs();
			if( isset( $langsAvail[ $lang ] ) )
			{
				$postIdOrig = Wp::SETPOSTLANG_IDORIG_DONTCHANGE;

				$hr = Wp::SetPostLang( $post -> ID, $lang, $postIdOrig );
				if( Gen::HrFail( $hr ) && $hr != Gen::E_NOTIMPL )
					$arrWarn[] = sprintf( esc_html_x( 'LangNotSet_%1$s%2$x', 'admin.Msg', 'seraphinite-post-docx-source' ), $lang, $hr );

			}
			else
				$arrWarn[] = sprintf( esc_html_x( 'LangNotAvail_%1$s', 'admin.Msg', 'seraphinite-post-docx-source' ), $lang );
		}
	}

	{
		$attrs = @$data[ 'blocks' ];
		if( $attrs )
		{
			foreach( $attrs as $attrKey => $attr )
			{
				$attr = wp_slash( $attr );

				$posSep = strpos( $attrKey, '.' );
				if( $posSep !== false )
				{
					$arrFldPath = substr( $attrKey, $posSep + 1 );
					$attrKey = substr( $attrKey, 0, $posSep );

					$attrArr = get_post_meta( $post -> ID, $attrKey, true );
					if( !is_array( $attrArr ) )
						$attrArr = array();
					Gen::SetArrField( $attrArr, $arrFldPath, $attr );
					update_post_meta( $post -> ID, $attrKey, $attrArr );
				}
				else
				{
					if( empty( $attr ) )
						delete_post_meta( $post -> ID, $attrKey );
					else
						update_post_meta( $post -> ID, $attrKey, $attr );
				}
			}
		}
	}

	{
		SetPostBindGuid( $post -> ID, @$data[ 'fileGuid' ] );
	}

	if( !empty( $argsUpdatePost ) )
	{
		$argsUpdatePost[ 'ID' ] = $post -> ID;

		$updRes = wp_update_post( wp_slash( $argsUpdatePost ), true );
		if( is_wp_error( $updRes ) )
			return( array( 'res' => 'fail', 'descr' => $updRes -> get_error_message() . ' Post content length: ' . @strlen( $text ) . '.' ) );
	}

	return( array( 'res' => 'ok' ) );
}

function GetPostsIdsBySlug( $slug, $type, $limit = 0 )
{
	global $wpdb;

	$res = array();

	if( empty( $slug ) || empty( $type ) )
		return( $res );

	$posts = $wpdb -> get_results( 'SELECT ID FROM ' . $wpdb -> posts . ' WHERE post_name=\'' . esc_sql( $slug ) . '\' AND post_type=\'' . esc_sql( $type ) . '\'' . ( $limit ? ( ' LIMIT ' . $limit ) : '' ), OBJECT_K );
	foreach( ( array )$posts as $post )
		$res[] = intval( $post -> ID );

	return( $res );
}

function GetPostsIdsByTitlePart( $title, $type, $limit = 5 )
{
	global $wpdb;

	$res = array();

	if( empty( $title ) || empty( $type ) )
		return( $res );

	$posts = $wpdb -> get_results( 'SELECT ID FROM ' . $wpdb -> posts . ' WHERE post_type=\'' . esc_sql( $type ) . '\' AND LOWER(post_title) LIKE \'' . esc_sql( like_escape( strtolower( $title ) ) ) . '%\'' . ( $limit ? ( ' LIMIT ' . $limit ) : '' ) );
	if( is_array( $posts ) )
		foreach( $posts as $post )
			$res[] = intval( $post -> ID );

	return( $res );
}

function UploadImage( $fileName, $contentType, $data, $postId = 0, $addToAttachments = true, $overwrite = false, $uploadDir = null, $uploadDirSubDir = null, $attrs = null, $resample = null, $lang = null, $delExtWebp = false )
{
	global $ctxUploadImage;

	$ctxUploadImage = array(
		'addToAttachments'		=> $addToAttachments,
		'overwrite'				=> $overwrite,
		'fileName'				=> $fileName,

		'uploadDirSubDir'		=> $uploadDirSubDir,
		'lang'					=> $lang
	);

	if( !current_user_can( 'upload_files' ) )
		return( new \WP_Error( 'access_denied', esc_html_x( 'UserNotUploadFiles', 'admin.Msg', 'seraphinite-post-docx-source' ) ) );

	if( $postId && !current_user_can( 'edit_post', $postId ) )
		return( new \WP_Error( 'access_denied', esc_html_x( 'UserNotEditPost', 'admin.Msg', 'seraphinite-post-docx-source' ) ) );

	global $post, $post_id, $_GET;

	$post_prev = $post;
	$post_id_prev = $post_id;

	if( $postId )
	{
		$post = null;
		$post_id = $postId;
	}

	$fileNamePathTmp = Wp::GetTempFile();

	add_filter( 'wp_unique_filename',			'seraph_pds\\_wp_unique_filename_UploadImage', 1, 4 );
	add_filter( 'pre_move_uploaded_file',		'seraph_pds\\_pre_move_uploaded_file_UploadImage', 1, 4 );
	add_filter( 'wp_handle_upload',				'seraph_pds\\_handle_upload_UploadImage', 99999, 2 );
	add_filter( 'upload_dir',					'seraph_pds\\_upload_dir_UploadImage' );

	$res = _UploadImage( $fileNamePathTmp, $contentType, $data, $postId, $addToAttachments, $overwrite, $attrs, $resample, $lang );

	remove_filter( 'upload_dir',				'seraph_pds\\_upload_dir_UploadImage' );
	remove_filter( 'wp_handle_upload',			'seraph_pds\\_handle_upload_UploadImage', 99999 );
	remove_filter( 'pre_move_uploaded_file',	'seraph_pds\\_pre_move_uploaded_file_UploadImage', 1 );
	remove_filter( 'wp_unique_filename',		'seraph_pds\\_wp_unique_filename_UploadImage', 1, 4 );

	@unlink( $fileNamePathTmp );

	if( $postId )
	{
		$post = $post_prev;
		$post_id = $post_id_prev;
	}

	return( $res );
}

function _wp_unique_filename_UploadImage( $filename, $ext, $dir, $unique_filename_callback )
{
	global $ctxUploadImage;

	if( $ctxUploadImage[ 'overwrite' ] )
		$filename = $ctxUploadImage[ 'fileName' ];
	else
		Fs::CreateEmptyFile( $dir . '/' . $filename );

	return( $filename );
}

function _pre_move_uploaded_file_UploadImage( $move_new_file, $file, $new_file, $type )
{
	global $ctxUploadImage;

	if( @rename( $file[ 'tmp_name' ], $new_file ) === false )
	{
		$ctxUploadImage[ 'error' ] = 'Can\'t write to "' . $new_file . '"';
		return( $move_new_file );
	}

	return( true );
}

function _handle_upload_UploadImage( $info, $action )
{
	global $ctxUploadImage;

	if( $ctxUploadImage[ 'overwrite' ] )
	{
		$newFileExt = Gen::GetFileExt( $info[ 'file' ] );
		if( Gen::GetFileExt( $ctxUploadImage[ 'fileName' ] ) != $newFileExt )
		{
			$oldFileName = Gen::GetFileName( $ctxUploadImage[ 'fileName' ], true );

			$correctedNewFilePathName = dirname( $info[ 'file' ] ) . '/' . $oldFileName . '.' . $newFileExt;
			if( @rename( $info[ 'file' ], $correctedNewFilePathName ) === false )
			{
				$ctxUploadImage[ 'error' ] = 'Can\'t write to "' . $correctedNewFilePathName . '"';
			}
			else
			{
				$info[ 'file' ] = $correctedNewFilePathName;
				$info[ 'url' ] = dirname( $info[ 'url' ] ) . '/' . $oldFileName . '.' . $newFileExt;
			}
		}
	}

	$ctxUploadImage[ 'url' ] = $info[ 'url' ];
	if( $ctxUploadImage[ 'addToAttachments' ] && $ctxUploadImage[ 'overwrite' ] )
		$ctxUploadImage[ 'prevAttachmentId' ] = Wp::GetAttachmentIdFromUrl( $info[ 'url' ], $ctxUploadImage[ 'lang' ] );

	if( !$ctxUploadImage[ 'addToAttachments' ] )
		$info[ 'error' ] = 'warnAttachmentWasNotPut';

	return( $info );
}

function _upload_dir_UploadImage( $uploads )
{
	global $ctxUploadImage;

	$uploadDirSubDir = @$ctxUploadImage[ 'uploadDirSubDir' ];
	if( $uploadDirSubDir !== null )
	{

		{
			$subDirLen = strlen( $uploads[ 'subdir' ] );
			if( $subDirLen )
			{
				$uploads[ 'subdir' ] = '';
				$uploads[ 'path' ] = substr( $uploads[ 'path' ], 0, strlen( $uploads[ 'path' ] ) - $subDirLen );
				$uploads[ 'url' ] = substr( $uploads[ 'url' ], 0, strlen( $uploads[ 'url' ] ) - $subDirLen );
			}
		}

		{
			$uploads[ 'subdir' ] .= $uploadDirSubDir;
			$uploads[ 'path' ] .= $uploadDirSubDir;
			$uploads[ 'url' ] .= $uploadDirSubDir;
		}
	}

	return( $uploads );
}

function _GetImageQuality( $mimeType, $quality = null )
{
	$qualityDef = 82;

	if( $quality === null )
	{
		$quality = apply_filters( 'wp_editor_set_quality', $qualityDef, $mimeType );
		if( 'image/jpeg' == $mimeType )
			$quality = apply_filters( 'jpeg_quality', $quality, 'image_resize' );
	}

	if( $quality < 0 || $quality > 100 )
		$quality = $qualityDef;

	if ( 0 === $quality )
		$quality = 1;

	return( $quality );
}

function _SaveImage( $img, $filename, $mimeType, $fileExt, $quality = null )
{
	if( 'image/gif' != $mimeType && 'image/jpeg' != $mimeType && 'image/png' != $mimeType )
	{
		$mimeType = 'image/png';
		$fileExt = $fileExt . '.png';
	}

	if( !Img::GetData( $img, $mimeType, array( 'q' => _GetImageQuality( $mimeType, $quality ) ), $filename ) )
		return( false );

	return( array( 'mime' => $mimeType, 'ext' => $fileExt ) );
}

function _IntRoundVal( $v, $round = true )
{
	return( intval( $round ? round( $v ) : $v ) );
}

function _ConvertImage( $fileNamePath, $resample, $mimeType, $fileExt, $force )
{
	if( !is_array( $resample ) )
		$resample = array();

	$crop = (isset($resample[ 'crop' ])?$resample[ 'crop' ]:null);
	if( !is_array( $crop ) )
		$crop = array();

	$cropSkip = (isset($crop[ 'skip' ])?$crop[ 'skip' ]:null);

	$resizeEmu = (isset($resample[ 'sizeEmu' ])?$resample[ 'sizeEmu' ]:null);

	if( !$force &&
		!(isset($crop[ 'l' ])?$crop[ 'l' ]:null) && !(isset($crop[ 't' ])?$crop[ 't' ]:null) && !(isset($crop[ 'r' ])?$crop[ 'r' ]:null) && !(isset($crop[ 'b' ])?$crop[ 'b' ]:null) && !$resizeEmu )
		return( null );

	if( (isset($crop[ 'l' ])?$crop[ 'l' ]:null) + (isset($crop[ 'r' ])?$crop[ 'r' ]:null) > 1.0 )
		$crop[ 'r' ] = 1.0 - (isset($crop[ 'l' ])?$crop[ 'l' ]:null);
	if( (isset($crop[ 't' ])?$crop[ 't' ]:null) + (isset($crop[ 'b' ])?$crop[ 'b' ]:null) > 1.0 )
		$crop[ 'b' ] = 1.0 - (isset($crop[ 't' ])?$crop[ 't' ]:null);

	$fileData = @file_get_contents( $fileNamePath );
	if( $fileData === false )
		return( new \WP_Error( 'invalid_image', esc_html( Wp::GetLocString( 'Could not read image size.' ) ), $fileNamePath ) );

	$info = Img::GetInfoFromData( $fileData, true );
	if( !$info || !Img::IsMimeRaster( $info[ 'mime' ] ) )
		return( new \WP_Error( 'invalid_image', esc_html( Wp::GetLocString( 'Could not read image size.' ) ), $fileNamePath ) );

	$cropPositive = array(
		'l' => (isset($crop[ 'l' ])?$crop[ 'l' ]:null) >= 0 ? _IntRoundVal( (isset($crop[ 'l' ])?$crop[ 'l' ]:null) * $info[ 'cx' ], false ) : 0,
		't' => (isset($crop[ 't' ])?$crop[ 't' ]:null) >= 0 ? _IntRoundVal( (isset($crop[ 't' ])?$crop[ 't' ]:null) * $info[ 'cy' ], false ) : 0,
		'r' => (isset($crop[ 'r' ])?$crop[ 'r' ]:null) >= 0 ? _IntRoundVal( (isset($crop[ 'r' ])?$crop[ 'r' ]:null) * $info[ 'cx' ], false ) : 0,
		'b' => (isset($crop[ 'b' ])?$crop[ 'b' ]:null) >= 0 ? _IntRoundVal( (isset($crop[ 'b' ])?$crop[ 'b' ]:null) * $info[ 'cy' ], false ) : 0,
	);

	$cropNegative = array(
		'l' => (isset($crop[ 'l' ])?$crop[ 'l' ]:null) < 0 ? _IntRoundVal( -(isset($crop[ 'l' ])?$crop[ 'l' ]:null) * $info[ 'cx' ], false ) : 0,
		't' => (isset($crop[ 't' ])?$crop[ 't' ]:null) < 0 ? _IntRoundVal( -(isset($crop[ 't' ])?$crop[ 't' ]:null) * $info[ 'cy' ], false ) : 0,
		'r' => (isset($crop[ 'r' ])?$crop[ 'r' ]:null) < 0 ? _IntRoundVal( -(isset($crop[ 'r' ])?$crop[ 'r' ]:null) * $info[ 'cx' ], false ) : 0,
		'b' => (isset($crop[ 'b' ])?$crop[ 'b' ]:null) < 0 ? _IntRoundVal( -(isset($crop[ 'b' ])?$crop[ 'b' ]:null) * $info[ 'cy' ], false ) : 0,
	);

	$rcSrc = array( 'x' => $cropPositive[ 'l' ], 'y' => $cropPositive[ 't' ], 'cx' => $info[ 'cx' ] - ( $cropPositive[ 'l' ] + $cropPositive[ 'r' ] ), 'cy' => $info[ 'cy' ] - ( $cropPositive[ 't' ] + $cropPositive[ 'b' ] ) );
	$sizeDst = array( 'cx' => $rcSrc[ 'cx' ] + ( $cropNegative[ 'l' ] + $cropNegative[ 'r' ] ), 'cy' => $rcSrc[ 'cy' ] + ( $cropNegative[ 't' ] + $cropNegative[ 'b' ] ) );

	$resizeCoeff = array( 'x' => 1.0, 'y' => 1.0 );
	if( $resizeEmu && $info[ 'dpiX' ] && $info[ 'dpiY' ] )
	{

		$sizeEmu2Pix = array( 'cx' => $resizeEmu[ 'cx' ] / 914400 * 96, 'cy' => $resizeEmu[ 'cy' ] / 914400 * 96 );
		$resizeCoeff = array( 'x' => $sizeEmu2Pix[ 'cx' ] / $sizeDst[ 'cx' ], 'y' => $sizeEmu2Pix[ 'cy' ] / $sizeDst[ 'cy' ] );
	}

	if( $cropSkip )
	{
		$cropPositive = array(
			'l' => 0,
			't' => 0,
			'r' => 0,
			'b' => 0,
		);

		$cropNegative = array(
			'l' => 0,
			't' => 0,
			'r' => 0,
			'b' => 0,
		);

		$rcSrc = array( 'x' => 0, 'y' => 0, 'cx' => $info[ 'cx' ], 'cy' => $info[ 'cy' ] );
		$sizeDst = array( 'cx' => $rcSrc[ 'cx' ], 'cy' => $rcSrc[ 'cy' ] );
	}

	$sizeDstRs = array( 'cx' => _IntRoundVal( $resizeCoeff[ 'x' ] * $sizeDst[ 'cx' ] ), 'cy' => _IntRoundVal( $resizeCoeff[ 'y' ] * $sizeDst[ 'cy' ] ) );

	$nameSuffix = '';
	{
		if( $cropPositive[ 'l' ] )
			$nameSuffix .= $cropPositive[ 'l' ] . 'l';
		if( $cropNegative[ 'l' ] )
			$nameSuffix .= $cropNegative[ 'l' ] . 'ln';

		if( $cropPositive[ 't' ] )
			$nameSuffix .= $cropPositive[ 't' ] . 't';
		if( $cropNegative[ 't' ] )
			$nameSuffix .= $cropNegative[ 't' ] . 'tn';

		if( $cropPositive[ 'r' ] )
			$nameSuffix .= $cropPositive[ 'r' ] . 'r';
		if( $cropNegative[ 'r' ] )
			$nameSuffix .= $cropNegative[ 'r' ] . 'rn';

		if( $cropPositive[ 'b' ] )
			$nameSuffix .= $cropPositive[ 'b' ] . 'b';
		if( $cropNegative[ 'b' ] )
			$nameSuffix .= $cropNegative[ 'b' ] . 'bn';

		if( $sizeDst[ 'cx' ] != $sizeDstRs[ 'cx' ] )
			$nameSuffix .= $sizeDstRs[ 'cx' ] . 'w';
		if( $sizeDst[ 'cy' ] != $sizeDstRs[ 'cy' ] )
			$nameSuffix .= $sizeDstRs[ 'cy' ] . 'h';

		if( empty( $nameSuffix ) )
		{
			if( !$force )
				return( null );
		}
		else
			$nameSuffix = '-' . $nameSuffix;
	}

	{
		if( !Gen::DoesFuncExist( 'imagecreatefromstring' ) )
			return( new \WP_Error( 'image_resize_crop_unsupported', esc_html( Wp::GetLocString( 'Image resizing/cropping is unsupported on this system.' ) ) ) );

		$img = Img::CreateFromData( $fileData );
		if( !$img )
			return( new \WP_Error( 'invalid_image', esc_html( Wp::GetLocString( 'File is not an image.' ) ), $fileNamePath ) );

		{
			$imgNew = Img::CreateCopyResample( $img,
				$sizeDstRs,
				$rcSrc,
				array( 'x' => _IntRoundVal( $resizeCoeff[ 'x' ] * $cropNegative[ 'l' ] ), 'y' => _IntRoundVal( $resizeCoeff[ 'y' ] * $cropNegative[ 't' ] ), 'cx' => _IntRoundVal( $resizeCoeff[ 'x' ] * $rcSrc[ 'cx' ] ), 'cy' => _IntRoundVal( $resizeCoeff[ 'y' ] * $rcSrc[ 'cy' ] ) ),
				$info[ 'mime' ] != 'image/png' ? 0xFFFFFF : null );

			if( $imgNew === null )
			{
				imagedestroy( $img );
				return( new \WP_Error( 'image_resize_error', esc_html( Wp::GetLocString( 'Image resize failed.' ) ), $fileNamePath ) );
			}

			imagedestroy( $img );
			$img = $imgNew;
			unset( $imgNew );
		}

		$resSave = _SaveImage( $img, $fileNamePath, $info[ 'mime' ], $fileExt );
		if( !$resSave )
		{
			imagedestroy( $img );
			return( new \WP_Error( 'image_save_error', esc_html( Wp::GetLocString( 'Image Editor Save Failed' ) ) ) );
		}

		imagedestroy( $img );

		$fileExt = $resSave[ 'ext' ];
		$mimeType = $resSave[ 'mime' ];
	}

	return( array( 'suffix' => $nameSuffix . '.' . $fileExt, 'mime' => $mimeType ) );
}

function _UploadImage( $fileNamePathTmp, $contentType, $data, $postId, $addToAttachments, $overwrite, $attrs, $resample, $lang )
{
	global $ctxUploadImage;

	$fileNamePathTmpSize = 0;
	{
		$file = @fopen( $fileNamePathTmp, 'wb' );
		if( $file === false )
			return( new \WP_Error( 'internal_error', sprintf( esc_html_x( 'NotCreateTmpFile_%1$s', 'admin.Msg', 'seraphinite-post-docx-source' ), $fileNamePathTmp ) ) );

		$err = null;
		if( Fs::StreamCopy( $data, $file ) != Gen::S_OK )
			$err = new \WP_Error( 'internal_error', sprintf( esc_html_x( 'NotCopyToTmpFile_%1$s', 'admin.Msg', 'seraphinite-post-docx-source' ), $fileNamePathTmp ) );

		$fileNamePathTmpSize = @filesize( $fileNamePathTmp );

		fclose( $file );
		if( $err )
			return( $err );
	}

	if( !$fileNamePathTmpSize )
		return( new \WP_Error( 'error_void_content', esc_html_x( 'NotUploadZeroContent', 'admin.Msg', 'seraphinite-post-docx-source' ) ) );

	$warnings = array();

	$forceConvert = $contentType == 'image/bmp';
	{
		$res = _ConvertImage( $fileNamePathTmp, $resample, $contentType, Gen::GetFileExt( $ctxUploadImage[ 'fileName' ] ), $forceConvert );
		if( is_wp_error( $res ) )
			$warnings[] = $res;
		else if( !empty( $res ) )
		{
			$ctxUploadImage[ 'fileName' ] = Gen::GetFileName( $ctxUploadImage[ 'fileName' ], true, true ) . $res[ 'suffix' ];
			$contentType = $res[ 'mime' ];
		}
	}

	$_FILES[ 'f' ] = array(
		'name'					=> $ctxUploadImage[ 'fileName' ],
		'tmp_name'				=> $fileNamePathTmp,
		'type'					=> $contentType,
		'size'					=> $fileNamePathTmpSize,
		'error'					=> 0,
	);

	$_FILES[ 'f' ] = apply_filters( 'wp_handle_upload_prefilter', $_FILES[ 'f' ] );

	$updateAttachment = $addToAttachments && $overwrite;

	$attachmentId = null;
	$metadata = null;
	{
		$res = media_handle_upload( 'f', $postId, array(), array( 'test_form' => false, 'action' => 'wp_handle_sideload' ) );
		if( is_wp_error( $res ) )
		{
			$warnAttachmentWasNotPut = $res -> get_error_code() == 'upload_error' && $res -> get_error_message() == 'warnAttachmentWasNotPut';
			if( !$warnAttachmentWasNotPut )
				return( $res );
		}
		else
		{
			if( !Gen::IsEmpty( @$ctxUploadImage[ 'error' ] ) )
				return( new \WP_Error( 'internal_error', @$ctxUploadImage[ 'error' ] ) );

			$attachmentId = $res;

			if( $updateAttachment )
			{
				$prevAttachmentId = $ctxUploadImage[ 'prevAttachmentId' ];

				if( $prevAttachmentId )
				{
					$newAttachment = get_post( $attachmentId, ARRAY_A );
					$newAttachmentMetadata = wp_get_attachment_metadata( $attachmentId );

					{
						update_attached_file( $newAttachment[ 'ID' ], '' );

						$res = wp_delete_attachment( $newAttachment[ 'ID' ], true );
						if( is_wp_error( $res ) )
							return( $res );
					}

					$newAttachment[ 'ID' ] = $prevAttachmentId;

					{
						$res = wp_update_post( $newAttachment, true );
						if( is_wp_error( $res ) )
							return( $res );
					}

					wp_update_attachment_metadata( $prevAttachmentId, $newAttachmentMetadata );

					$attachmentId = $prevAttachmentId;
					$metadata = $newAttachmentMetadata;
				}
				else
					$metadata = wp_get_attachment_metadata( $attachmentId );
			}
			else
				$metadata = wp_get_attachment_metadata( $attachmentId );

			if( $addToAttachments )
			{

				{
					if( $lang !== null )
					{
						$hr = Wp::SetPostLang( $attachmentId, $lang );
						if( Gen::HrFail( $hr ) && $hr != Gen::E_NOTIMPL )
							$warnings[] = new \WP_Error( 'internal_error', 'Can\'t set \'' . $lang . '\' language: ' . sprintf( '0x%08X', $hr ) );
					}
				}

				{
					$updateData = array();

					$title = @$attrs[ 'title' ];
					if( $title )
						$updateData[ 'post_title' ] = $title;

					$altText = @$attrs[ 'altText' ];
					if( $altText )
						$updateData[ '_wp_attachment_image_alt' ] = $altText;

					$description = @$attrs[ 'description' ];
					if( $description )
						$updateData[ 'post_content' ] = $description;

					$caption = @$attrs[ 'caption' ];
					if( $caption )
						$updateData[ 'post_excerpt' ] = $caption;

					if( count( $updateData ) )
					{
						$res = Wp::UpdateAttachment( $attachmentId, wp_slash( $updateData ), true );
						if( is_wp_error( $res ) )
							return( $res );
					}
				}
			}

			if( !wp_prepare_attachment_for_js( $attachmentId ) )
				return( new \WP_Error( 'internal_error', 'prepare_attachment_for_js_failed' ) );
		}
	}

	return( array( 'url' => Net::Url2Uri( $ctxUploadImage[ 'url' ], true ), 'attachmentId' => $attachmentId, 'metadata' => $metadata, 'warnings' => $warnings ) );
}

