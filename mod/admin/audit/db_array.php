<?php
//DB Array Initilizing
$DB_ARRAY = array();

//TABLES contains information about tables
$DB_ARRAY['TABLES'] = array();

//TABLE: core_enum
$DB_ARRAY['TABLES']['core_enum'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_enum']['ENGINE']  = "InnoDB";
//Primary Key for core_enum
$DB_ARRAY['TABLES']['core_enum']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_enum']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['name'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['name']['TYPE']    = "varchar(128)";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['name']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['parent_id'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['parent_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['parent_id']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['parent_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['parent_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_default_sw'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_default_sw']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_default_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_default_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_default_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_active_sw'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_active_sw']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_active_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_active_sw']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['is_active_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['seq'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['seq']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['seq']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['seq']['DEFAULT'] = "0";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['seq']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['global_id'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['global_id']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['global_id']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['global_id']['DEFAULT'] = "NULL";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['global_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['custom_field'] = array();
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['custom_field']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['custom_field']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_enum']['COLUMNS']['custom_field']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_enum']['FK'] = array();

$DB_ARRAY['TABLES']['core_enum']['FK']['core_enum_fk']['COL_NAME']   = "parent_id";
$DB_ARRAY['TABLES']['core_enum']['FK']['core_enum_fk']['REF_TABLE']  = "core_enum";
$DB_ARRAY['TABLES']['core_enum']['FK']['core_enum_fk']['REF_COLUMN'] = "id";
$DB_ARRAY['TABLES']['core_enum']['FK']['core_enum_fk']['ON_DELETE']  = "CASCADE";
$DB_ARRAY['TABLES']['core_enum']['FK']['core_enum_fk']['ON_UPDATE']  = "CASCADE";

//Table Key array
$DB_ARRAY['TABLES']['core_enum']['KEY'] = array();
$DB_ARRAY['TABLES']['core_enum']['KEY']['parent_id'] = array();
$DB_ARRAY['TABLES']['core_enum']['KEY']['parent_id']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_enum']['KEY']['parent_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_enum']['KEY']['parent_id']['COLUMNS']['parent_id'] = "0";
$DB_ARRAY['TABLES']['core_enum']['KEY']['parent_id']['COLUMNS']['name']    = "1";

$DB_ARRAY['TABLES']['core_enum']['KEY']['global_id'] = array();
$DB_ARRAY['TABLES']['core_enum']['KEY']['global_id']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_enum']['KEY']['global_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_enum']['KEY']['global_id']['COLUMNS']['global_id'] = "0";

//TABLE: core_controls
$DB_ARRAY['TABLES']['core_controls'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_controls']['ENGINE']  = "MyISAM";
//Primary Key for core_log
//Define array for columns
$DB_ARRAY['TABLES']['core_controls']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['tbl'] = array();
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['tbl']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['tbl']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['tbl']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['tbl']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['keyfield'] = array();
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['keyfield']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['keyfield']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['keyfield']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['keyfield']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['val'] = array();
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['val']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['val']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['val']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['val']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastupdate']['TYPE']    = "varchar(30)";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastupdate']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastuser']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastuser']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_controls']['COLUMNS']['lastuser']['EXTRA']   = "";

//TABLE: core_log
$DB_ARRAY['TABLES']['core_log'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_log']['ENGINE']  = "MyISAM";
//Primary Key for core_log
$DB_ARRAY['TABLES']['core_log']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_log']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['user_id'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['user_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['user_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['user_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['user_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['sid'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['sid']['TYPE']    = "varchar(128)";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['sid']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['sid']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['sid']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['action'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['action']['TYPE']    = "longtext";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['action']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['action']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['action']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['lastupdate']['EXTRA']   = "on update CURRENT_TIMESTAMP";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['query'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['query']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['query']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['query']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['query']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['request_method'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['request_method']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['request_method']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['request_method']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['request_method']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['remote_port'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['remote_port']['TYPE']    = "mediumint(9)";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['remote_port']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['remote_port']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['remote_port']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_log']['COLUMNS']['ip'] = array();
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['ip']['TYPE'] = "varchar(20)";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['ip']['NULL'] = "YES";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['ip']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_log']['COLUMNS']['ip']['EXTRA'] = "";

//Table Key array
$DB_ARRAY['TABLES']['core_log']['KEY'] = array();
$DB_ARRAY['TABLES']['core_log']['KEY']['user_id'] = array();
$DB_ARRAY['TABLES']['core_log']['KEY']['user_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_log']['KEY']['user_id']['COLUMNS']['user_id'] = "0";

$DB_ARRAY['TABLES']['core_log']['KEY']['session_id'] = array();
$DB_ARRAY['TABLES']['core_log']['KEY']['session_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_log']['KEY']['session_id']['COLUMNS']['sid']     = "0";


//TABLE: core_modules
$DB_ARRAY['TABLES']['core_modules'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_modules']['ENGINE']  = "InnoDB";
//Primary Key for core_modules
$DB_ARRAY['TABLES']['core_modules']['PRIMARY_KEY'] = "m_id";
//Define array for columns
$DB_ARRAY['TABLES']['core_modules']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_id'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_name'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_name']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_name']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['m_name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['version'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['version']['TYPE']    = "varchar(10)";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['version']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['version']['DEFAULT'] = "1.0.0";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['version']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['module_id'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['module_id']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['module_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['module_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['module_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['visible'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['visible']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['visible']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['visible']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['visible']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_system'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_system']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_system']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_system']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_system']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_public'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_public']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_public']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_public']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['is_public']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['isset_home_page'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['isset_home_page']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['isset_home_page']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['isset_home_page']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['isset_home_page']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['dependencies'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['dependencies']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['dependencies']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['dependencies']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['dependencies']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['seq'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['seq']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['seq']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['seq']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['seq']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_default'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_default']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_default']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_default']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_default']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_add'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_add']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_add']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_add']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['access_add']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['uninstall'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['uninstall']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['uninstall']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['uninstall']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['uninstall']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['files_hash'] = array();
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['files_hash']['TYPE']    = "longtext";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['files_hash']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['files_hash']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_modules']['COLUMNS']['files_hash']['EXTRA']   = "";


//Table Key array
$DB_ARRAY['TABLES']['core_modules']['KEY'] = array();
$DB_ARRAY['TABLES']['core_modules']['KEY']['m_name'] = array();
$DB_ARRAY['TABLES']['core_modules']['KEY']['m_name']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_modules']['KEY']['m_name']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_modules']['KEY']['m_name']['COLUMNS']['m_name']  = "0";

$DB_ARRAY['TABLES']['core_modules']['KEY']['module_id'] = array();
$DB_ARRAY['TABLES']['core_modules']['KEY']['module_id']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_modules']['KEY']['module_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_modules']['KEY']['module_id']['COLUMNS']['module_id'] = "0";


//TABLE: core_available_modules
$DB_ARRAY['TABLES']['core_available_modules'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_available_modules']['ENGINE']  = "InnoDB";
//Primary Key for core_available_modules
$DB_ARRAY['TABLES']['core_available_modules']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_id'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_id']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_group'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_group']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_group']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_group']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['module_group']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['name'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['name']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['name']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['version'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['version']['TYPE']    = "varchar(10)";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['version']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['version']['DEFAULT'] = "1.0.0";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['version']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['descr'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['descr']['TYPE']    = "varchar(128)";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['descr']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['descr']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['descr']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['data'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['data']['TYPE']    = "longblob";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['data']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['data']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['data']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['install_info'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['install_info']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['install_info']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['install_info']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['install_info']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['readme'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['readme']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['readme']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['readme']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['readme']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['files_hash'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['files_hash']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['files_hash']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['files_hash']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_available_modules']['COLUMNS']['files_hash']['EXTRA']   = "";

//core_available_modules Key array
$DB_ARRAY['TABLES']['core_available_modules']['KEY']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['KEY']['lastuser']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_available_modules']['KEY']['lastuser']['COLUMNS']['lastuser']    = "0";

//TABLE: core_roles
$DB_ARRAY['TABLES']['core_roles'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_roles']['ENGINE']  = "InnoDB";
//Primary Key for core_roles
$DB_ARRAY['TABLES']['core_roles']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_roles']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['name'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['name']['TYPE']    = "varchar(255)";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['name']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['is_active_sw'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['is_active_sw']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['is_active_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['is_active_sw']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['is_active_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['description'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['description']['TYPE']    = "varchar(255)";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['description']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['description']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['description']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['date_added'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['date_added']['TYPE']    = "datetime";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['date_added']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['date_added']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['date_added']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['position'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['position']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['position']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['position']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['position']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access_add'] = array();
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access_add']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access_add']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access_add']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_roles']['COLUMNS']['access_add']['EXTRA']   = "";


//Table Key array
$DB_ARRAY['TABLES']['core_roles']['KEY'] = array();
$DB_ARRAY['TABLES']['core_roles']['KEY']['name'] = array();
$DB_ARRAY['TABLES']['core_roles']['KEY']['name']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_roles']['KEY']['name']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_roles']['KEY']['name']['COLUMNS']['name']    = "0";


//TABLE: core_session
$DB_ARRAY['TABLES']['core_session'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_session']['ENGINE']  = "MyISAM";
//Primary Key for core_session
$DB_ARRAY['TABLES']['core_session']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_session']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['sid'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['sid']['TYPE']    = "varchar(128)";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['sid']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['sid']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['sid']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['login_time'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['login_time']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['login_time']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['login_time']['DEFAULT'] = "NULL";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['login_time']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['logout_time'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['logout_time']['TYPE']    = "datetime";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['logout_time']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['logout_time']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['logout_time']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['user_id'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['user_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['user_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['user_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['user_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['ip'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['ip']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['ip']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['ip']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['ip']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_expired_sw'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_expired_sw']['TYPE']    = "enum('N','Y')";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_expired_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_expired_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_expired_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['last_activity'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['last_activity']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['last_activity']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['last_activity']['DEFAULT'] = "NULL";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['last_activity']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['crypto_sw'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['crypto_sw']['TYPE']    = "enum('N','Y')";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['crypto_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['crypto_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['crypto_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_kicked_sw'] = array();
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_kicked_sw']['TYPE']    = "enum('N','Y')";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_kicked_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_kicked_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_session']['COLUMNS']['is_kicked_sw']['EXTRA']   = "";


//Table Key array
$DB_ARRAY['TABLES']['core_session']['KEY'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['user_id'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['user_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['user_id']['COLUMNS']['user_id'] = "0";

$DB_ARRAY['TABLES']['core_session']['KEY']['sid'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['sid']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['sid']['COLUMNS']['sid']     = "0";

$DB_ARRAY['TABLES']['core_session']['KEY']['is_expired_sw'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['is_expired_sw']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['is_expired_sw']['COLUMNS']['is_expired_sw']     = "0";

$DB_ARRAY['TABLES']['core_session']['KEY']['is_kicked_sw'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['is_kicked_sw']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_session']['KEY']['is_kicked_sw']['COLUMNS']['is_kicked_sw']     = "0";

//TABLE: core_settings
$DB_ARRAY['TABLES']['core_settings'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_settings']['ENGINE']  = "InnoDB";
//Primary Key for core_settings
$DB_ARRAY['TABLES']['core_settings']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_settings']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['system_name'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['system_name']['TYPE']    = "varchar(255)";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['system_name']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['system_name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['system_name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['value'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['value']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['value']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['value']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['value']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['code'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['code']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['code']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['code']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['code']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['visible'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['visible']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['visible']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['visible']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['visible']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['type'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['type']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['type']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['type']['DEFAULT'] = "text";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['type']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_custom_sw'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_custom_sw']['TYPE']    = "enum('N','Y')";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_custom_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_custom_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_custom_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_personal_sw'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_personal_sw']['TYPE']    = "enum('N','Y')";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_personal_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_personal_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['is_personal_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['seq'] = array();
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['seq']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['seq']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['seq']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_settings']['COLUMNS']['seq']['EXTRA']   = "";

//Table Key array
$DB_ARRAY['TABLES']['core_settings']['KEY'] = array();
$DB_ARRAY['TABLES']['core_settings']['KEY']['code'] = array();
$DB_ARRAY['TABLES']['core_settings']['KEY']['code']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_settings']['KEY']['code']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_settings']['KEY']['code']['COLUMNS']['code']    = "0";


//TABLE: core_submodules
$DB_ARRAY['TABLES']['core_submodules'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_submodules']['ENGINE']  = "InnoDB";
//Primary Key for core_submodules
$DB_ARRAY['TABLES']['core_submodules']['PRIMARY_KEY'] = "sm_id";
//Define array for columns
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_id'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_name'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_name']['TYPE']    = "varchar(128)";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_name']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_name']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_name']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['visible'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['visible']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['visible']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['visible']['DEFAULT'] = "Y";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['visible']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['m_id'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['m_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['m_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['m_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['m_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_path'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_path']['TYPE']    = "varchar(255)";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_path']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_path']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_path']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_key'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_key']['TYPE']    = "varchar(20)";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_key']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_key']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['sm_key']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['seq'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['seq']['TYPE']    = "int(11)";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['seq']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['seq']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_default'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_default']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_default']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_default']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_default']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_add'] = array();
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_add']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_add']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_add']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_submodules']['COLUMNS']['access_add']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_submodules']['FK'] = array();

$DB_ARRAY['TABLES']['core_submodules']['FK']['core_submodules_fk']['COL_NAME']   = "m_id";
$DB_ARRAY['TABLES']['core_submodules']['FK']['core_submodules_fk']['REF_TABLE']  = "core_modules";
$DB_ARRAY['TABLES']['core_submodules']['FK']['core_submodules_fk']['REF_COLUMN'] = "m_id";
$DB_ARRAY['TABLES']['core_submodules']['FK']['core_submodules_fk']['ON_DELETE']  = "CASCADE";
$DB_ARRAY['TABLES']['core_submodules']['FK']['core_submodules_fk']['ON_UPDATE']  = "CASCADE";

//Table Key array
$DB_ARRAY['TABLES']['core_submodules']['KEY'] = array();
$DB_ARRAY['TABLES']['core_submodules']['KEY']['m_id'] = array();
$DB_ARRAY['TABLES']['core_submodules']['KEY']['m_id']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_submodules']['KEY']['m_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_submodules']['KEY']['m_id']['COLUMNS']['m_id']    = "0";
$DB_ARRAY['TABLES']['core_submodules']['KEY']['m_id']['COLUMNS']['sm_key']  = "1";


//TABLE: core_users
$DB_ARRAY['TABLES']['core_users'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_users']['ENGINE']  = "InnoDB";
//Primary Key for core_users
$DB_ARRAY['TABLES']['core_users']['PRIMARY_KEY'] = "u_id";
//Define array for columns
$DB_ARRAY['TABLES']['core_users']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_id'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_login'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_login']['TYPE']    = "varchar(120)";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_login']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_login']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_login']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_pass'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_pass']['TYPE']    = "varchar(36)";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_pass']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_pass']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['u_pass']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['visible'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['visible']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['visible']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['visible']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['visible']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_added'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_added']['TYPE']    = "datetime";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_added']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_added']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_added']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['email'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['email']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['email']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['email']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['email']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_admin_sw'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_admin_sw']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_admin_sw']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_admin_sw']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_admin_sw']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['certificate'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['certificate']['TYPE']    = "text";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['certificate']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['certificate']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['certificate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['role_id'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['role_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['role_id']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['role_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['role_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['reg_key'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['reg_key']['TYPE']    = "varchar(255)";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['reg_key']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['reg_key']['DEFAULT'] = "NULL";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['reg_key']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_expired'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_expired']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_expired']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_expired']['DEFAULT'] = "NULL";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['date_expired']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_email_wrong'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_email_wrong']['TYPE']    = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_email_wrong']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_email_wrong']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_email_wrong']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_pass_changed'] = array();
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_pass_changed']['TYPE'] = "enum('Y','N')";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_pass_changed']['NULL'] = "NO";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_pass_changed']['DEFAULT'] = "N";
$DB_ARRAY['TABLES']['core_users']['COLUMNS']['is_pass_changed']['EXTRA'] = "";


//Table Key array
$DB_ARRAY['TABLES']['core_users']['KEY']['u_login'] = array();
$DB_ARRAY['TABLES']['core_users']['KEY']['u_login']['TYPE']    = "UNIQ";
$DB_ARRAY['TABLES']['core_users']['KEY']['u_login']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_users']['KEY']['u_login']['COLUMNS']['u_login'] = "0";


//TABLE: core_users_profile
$DB_ARRAY['TABLES']['core_users_profile'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_users_profile']['ENGINE']  = "InnoDB";
//Primary Key for core_users_profile
$DB_ARRAY['TABLES']['core_users_profile']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['user_id'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['user_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['user_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['user_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['user_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastname'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastname']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastname']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastname']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastname']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['firstname'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['firstname']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['firstname']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['firstname']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['firstname']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['middlename'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['middlename']['TYPE']    = "varchar(60)";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['middlename']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['middlename']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['middlename']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastupdate'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastupdate']['TYPE']    = "timestamp";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastupdate']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastupdate']['DEFAULT'] = "CURRENT_TIMESTAMP";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastupdate']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_profile']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_profile']['FK'] = array();

$DB_ARRAY['TABLES']['core_users_profile']['FK']['core_users_profile_fk']['COL_NAME']   = "user_id";
$DB_ARRAY['TABLES']['core_users_profile']['FK']['core_users_profile_fk']['REF_TABLE']  = "core_users";
$DB_ARRAY['TABLES']['core_users_profile']['FK']['core_users_profile_fk']['REF_COLUMN'] = "u_id";
$DB_ARRAY['TABLES']['core_users_profile']['FK']['core_users_profile_fk']['ON_DELETE']  = "CASCADE";
$DB_ARRAY['TABLES']['core_users_profile']['FK']['core_users_profile_fk']['ON_UPDATE']  = "CASCADE";

//Table Key array
$DB_ARRAY['TABLES']['core_users_profile']['KEY'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['KEY']['user_id'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['KEY']['user_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_users_profile']['KEY']['user_id']['COLUMNS']['user_id'] = "0";


//TABLE: core_users_roles
$DB_ARRAY['TABLES']['core_users_roles'] = array();
//Table Enginge Definition
$DB_ARRAY['TABLES']['core_users_roles']['ENGINE']  = "InnoDB";
//Primary Key for core_users_roles
$DB_ARRAY['TABLES']['core_users_roles']['PRIMARY_KEY'] = "id";
//Define array for columns
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS'] = array();

$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['id'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['id']['EXTRA']   = "auto_increment";

$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['user_id'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['user_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['user_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['user_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['user_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['role_id'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['role_id']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['role_id']['NULL']    = "NO";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['role_id']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['role_id']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['lastuser'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['lastuser']['TYPE']    = "int(11) unsigned";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['lastuser']['NULL']    = "YES";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['lastuser']['DEFAULT'] = "";
$DB_ARRAY['TABLES']['core_users_roles']['COLUMNS']['lastuser']['EXTRA']   = "";

$DB_ARRAY['TABLES']['core_users_roles']['FK'] = array();

$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk']['COL_NAME'] = "user_id";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk']['REF_TABLE'] = "core_users";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk']['REF_COLUMN'] = "u_id";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk']['ON_DELETE'] = "CASCADE";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk']['ON_UPDATE'] = "CASCADE";

$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk2']['COL_NAME'] = "role_id";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk2']['REF_TABLE'] = "core_roles";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk2']['REF_COLUMN'] = "id";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk2']['ON_DELETE'] = "RESTRICT";
$DB_ARRAY['TABLES']['core_users_roles']['FK']['core_users_roles_fk2']['ON_UPDATE'] = "RESTRICT";

//Table Key array
$DB_ARRAY['TABLES']['core_users_roles']['KEY'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['user_id'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['user_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['user_id']['COLUMNS']['user_id'] = "0";
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['role_id'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['role_id']['COLUMNS'] = array();
$DB_ARRAY['TABLES']['core_users_roles']['KEY']['role_id']['COLUMNS']['role_id'] = "0";
?>