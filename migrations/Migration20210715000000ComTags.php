<?php

use Hubzero\Content\Migration\Base;

/**
 * Migration script modifying length of label and tbl columns in #__tags_object table
 **/
class Migration20210715000000ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
		if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'label'))
		{
            $query = "ALTER TABLE `#__tags_object` ALTER COLUMN `label` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
			$this->db->setQuery($query);
			$this->db->query();
		}

        if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'tbl'))
		{
            $query = "ALTER TABLE `#__tags_object` ALTER COLUMN `tbl` VARCHAR(100) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'label'))
		{
            $query = "ALTER TABLE `#__tags_object` ALTER COLUMN `label` VARCHAR(30) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
			$this->db->setQuery($query);
			$this->db->query();
		}

        if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'tbl'))
		{
            $query = "ALTER TABLE `#__tags_object` ALTER COLUMN `tbl` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL DEFAULT ''";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}
}
