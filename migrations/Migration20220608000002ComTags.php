<?php

use Hubzero\Content\Migration\Base;

/**
 * Migration script modifying unique indices for jos_tags table.
 *  Make (tag, admin) unique but (tag) non-unique.
 **/
class Migration20220608000002ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__tags') && 
            $this->db->tableHasKey('#__tags', 'idx_tag') &&
           !$this->db->tableHasKey('#__tags', 'idx_tag_admin'))
		{
			$query = "ALTER TABLE `#__tags` DROP INDEX `idx_tag`, 
                      ADD INDEX `idx_tag` (`tag`),
                      ADD UNIQUE KEY `idx_tag_admin` (`tag`, `admin`)";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
        if ($this->db->tableExists('#__tags') && 
            $this->db->tableHasKey('#__tags', 'idx_tag') &&
            $this->db->tableHasKey('#__tags', 'idx_tag_admin'))
		{
			$query = "ALTER TABLE `#__tags` DROP INDEX `idx_tag`, 
                      DROP `idx_tag_admin`,
                      ADD UNIQUE KEY `idx_tag` (`tag`)";
			$this->db->setQuery($query);
			$this->db->query();
		}
    }
}
