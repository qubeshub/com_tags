<?php

use Hubzero\Content\Migration\Base;

/**
 * Migration script adding label as unique key to tags object table
 **/
class Migration20210602000000ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
		if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'unique_tag_per_obj'))
		{
			$query = "ALTER TABLE `#__tags_object` DROP INDEX unique_tag_per_obj, 
                ADD UNIQUE KEY `unique_tag_per_obj` (`objectid`, `tagid`, `tbl`, `label`)";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__tags_object') && $this->db->tableHasKey('#__tags_object', 'unique_tag_per_obj'))
		{
			$query = "ALTER TABLE `#__tags_object` DROP INDEX unique_tag_per_obj, 
                ADD UNIQUE KEY `unique_tag_per_obj` (`objectid`, `tagid`, `tbl`)";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}
}
