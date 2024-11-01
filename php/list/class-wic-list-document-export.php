<?php
/*
* class-wic-list-document-export.php
*
* 
*/ 

class WIC_List_Document_Export {

	public static function do_document_download ( $dummy, $id ) {

		
		global $wpdb;
		$activity_table = $wpdb->prefix . 'wic_activity';
		$sql = "SELECT file_name, file_content from $activity_table WHERE ID = $id";
		$file_row = $wpdb->get_results ( $sql );
		$file_name = $file_row[0]->file_name;

		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Description: File Transfer');
		header("Content-type: application/octet-stream");
		header("Content-Disposition: attachment; filename={$file_name}");
		header("Expires: 0");
		header("Pragma: public");

		file_put_contents( 'php://output', $file_row[0]->file_content ); // writing plain text files; good to support txt 

		exit;
	}


}