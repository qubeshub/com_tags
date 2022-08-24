<?php

use Hubzero\Content\Migration\Base;

/**
 * Migration script creating other column in focus area table and changing label length
 **/
class Migration20220608000001ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__focus_areas')) 
        {
            # Add other column
            if (!$this->db->tableHasField('#__focus_areas', 'other'))
            {
                $query = "ALTER TABLE `#__focus_areas` ADD COLUMN other TINYINT(1) DEFAULT 0";
                $this->db->setQuery($query);
                $this->db->query();
            }

            # Modify length of label column to 511
            if ($this->db->tableHasField('#__focus_areas', 'label'))
            {
                $query = "ALTER TABLE `#__focus_areas` MODIFY COLUMN label VARCHAR(511) DEFAULT ''";
                $this->db->setQuery($query);
                $this->db->query();
            }
        }
	}

	/**
	 * Down
	 **/
	public function down()
	{
        if ($this->db->tableExists('#__focus_areas')) 
        {
            # Delete other column
            if ($this->db->tableHasField('#__focus_areas', 'other'))
            {
                $query = "ALTER TABLE `#__focus_areas` DROP COLUMN other";
                $this->db->setQuery($query);
                $this->db->query();
            }

            # Modify length of label column to 255
            if ($this->db->tableHasField('#__focus_areas', 'label'))
            {
                $query = "ALTER TABLE `#__focus_areas` MODIFY COLUMN label VARCHAR(255) DEFAULT ''";
                $this->db->setQuery($query);
                $this->db->query();
            }
        }
	}
}
