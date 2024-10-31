<?php

namespace seraph_pds;

if( !defined( 'ABSPATH' ) )
	exit;

require( __DIR__ . '/Cmn/Gen.php' );
require( __DIR__ . '/Cmn/Ui.php' );
require( __DIR__ . '/Cmn/Fs.php' );
require( __DIR__ . '/Cmn/Img.php' );

require( __DIR__ . '/Cmn/Plugin.php' );

require( __DIR__ . '/options.php' );

const PLUGIN_DATA_VER								= 1;
const PLUGIN_EULA_VER								= 1;

function _ShowOperateOptionsBlock_LogContent( $content )
{
	return( $content );
}

function _ShowOperateOptionsBlock_UpdateBtn( $content, $primary = false, $nameId = null, $classesEx = null, $type = 'submit', $attrs = null )
{
	return( Ui::Button( $content, $primary, $nameId, $classesEx, $type, $attrs ) );
}

function GetPostsAvailableCategories( $postTypes = NULL )
{
	if( empty( $postTypes ) )
		$postTypes = GetCompatiblePostsTypes();
	return( Wp::GetPostsAvailableTaxonomies( Wp::POST_TAXONOMY_CLASS_CATEGORIES, $postTypes ) );
}

function GetCompatiblePostsTypes()
{
	return( Wp::GetSupportsPostsTypes( array( 'editor' ) ) );
}

function GetSepSymb()
{
	if( Gen::DoesFuncExist( 'RankMath\Helper::replace_vars' ) )
		return( \RankMath\Helper::replace_vars( '%sep%', null ) );
	if( Gen::DoesFuncExist( 'wpseo_replace_vars' ) )
		return( wpseo_replace_vars( '%%sep%%', null ) );

	return( '-' );
}

function getUrl( $url, $body = false )
{

	$prms = array( 'sslverify' => false, 'timeout' => 30, 'user-agent' => 'Mozilla/99999.9 AppleWebKit/9999999.99 (KHTML, like Gecko) Chrome/999999.0.9999.99 Safari/9999999.99', 'reject_unsafe_urls' => true );

	$requestRes = Wp::RemoteGet( $url, $prms );

	if( is_wp_error( $requestRes ) )
		return( array( 'hr' => Gen::E_FAIL, 'descr' => $requestRes -> get_error_message() ) );

	$httpStatus = wp_remote_retrieve_response_code( $requestRes );
	if( !( $httpStatus >= 200 && $httpStatus < 400 || $httpStatus == 403 || $httpStatus == 423 ) )
		return( array( 'hr' => Gen::S_FALSE, 'httpCode' => $httpStatus ) );

	$res = array( 'hr' => Gen::S_OK );
	if( $body )
		$res[ 'body' ] = wp_remote_retrieve_body( $requestRes );

	return( $res );
}

function _dublHdr( $requestRes, $tag )
{
	$tagVal = wp_remote_retrieve_header( $requestRes, $tag );
	if( !empty( $tagVal ) )
		header( $tag . ': ' . $tagVal );
}

function getUrlRaw( $url )
{
	$requestRes = Wp::RemoteGet( $url, array( 'sslverify' => false, 'timeout' => 30, 'user-agent' => 'Mozilla/99999.9 AppleWebKit/9999999.99 (KHTML, like Gecko) Chrome/999999.0.9999.99 Safari/9999999.99', 'reject_unsafe_urls' => true,  ) );

	if( is_wp_error( $requestRes ) )
	{
		header( 'HTTP/1.0 505 Internal Server Error' );
		return;
	}

	header( 'HTTP/1.0 ' . wp_remote_retrieve_response_code( $requestRes ) );

	_dublHdr( $requestRes, 'content-type' );

	_dublHdr( $requestRes, 'content-disposition' );

	print( wp_remote_retrieve_body( $requestRes ) );
}

function _IsGutenbergPage()
{
	if( Gen::DoesFuncExist( 'is_gutenberg_page' ) )
	    return( is_gutenberg_page() );

	{
		global $current_screen;

		if( !isset( $current_screen ) )
			$current_screen = get_current_screen();

		if( method_exists( $current_screen, 'is_block_editor' ) )
			return( $current_screen -> is_block_editor() );
	}

	return( false );
}

function _IsGutenbergDefault( $postType )
{
	$postCont = Wp::CreateFakePostContainer( $postType );

	if( Gen::DoesFuncExist( 'gutenberg_can_edit_post' ) && gutenberg_can_edit_post( $postCont -> post ) )
		return( true );

	if( Gen::DoesFuncExist( 'use_block_editor_for_post' ) && use_block_editor_for_post( $postCont -> post ) )
		return( true );

	return( false );
}

function _IsGutenbergActive()
{
	$postType = 'post';

	if( Gen::DoesFuncExist( 'gutenberg_can_edit_post_type' ) && gutenberg_can_edit_post_type( $postType ) )
		return( true );

	if( Gen::DoesFuncExist( 'use_block_editor_for_post_type' ) && use_block_editor_for_post_type( $postType ) )
		return( true );

	return( false );
}

_PreventUpdateDocBindMeta_Enable( true );

function GetPostBindGuid( $postId )
{
	return( get_post_meta( $postId, '_seraph_pds_BindGuid', true ) );
}

function SetPostBindGuid( $postId, $fileGuid )
{
	if( $fileGuid === null )
		return;

	_PreventUpdateDocBindMeta_Enable( false );

	if( empty( $fileGuid ) )
		delete_post_meta( $postId, '_seraph_pds_BindGuid' );
	else if( get_post_meta( $postId, '_seraph_pds_BindGuid', true ) != $fileGuid )
		update_post_meta( $postId, '_seraph_pds_BindGuid', $fileGuid );

	_PreventUpdateDocBindMeta_Enable( true );
}

function _PreventUpdateDocBindMeta_Enable( $enable )
{
	if( $enable )
	{
		add_filter( 'add_post_metadata', 'seraph_pds\\_PreventUpdateDocBindMeta', 99999, 5 );
		add_filter( 'update_post_metadata', 'seraph_pds\\_PreventUpdateDocBindMeta', 99999, 5 );
	}
	else
	{
		remove_filter( 'update_post_metadata', 'seraph_pds\\_PreventUpdateDocBindMeta', 99999 );
		remove_filter( 'add_post_metadata', 'seraph_pds\\_PreventUpdateDocBindMeta', 99999 );
	}
}

function _PreventUpdateDocBindMeta( $check, $object_id, $meta_key, $meta_value, $unique_or_prev_value )
{
	return( $meta_key == '_seraph_pds_BindGuid' ? true : $check );
}

