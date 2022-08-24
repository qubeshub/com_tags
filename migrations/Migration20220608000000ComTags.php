<?php

use Hubzero\Content\Migration\Base;

/**
 * Migration script copying mandatory and multiple info from focus area to instantiation
 **/
class Migration20220608000000ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__focus_areas') && 
            $this->db->tableExists('#__focus_area_publication_master_type_rel') &&
            $this->db->tableHasField('#__focus_areas', 'mandatory_depth') &&
            $this->db->tableHasField('#__focus_areas', 'multiple_depth') &&
            !$this->db->tableHasField('#__focus_area_publication_master_type_rel', 'mandatory_depth') &&
            !$this->db->tableHasField('#__focus_area_publication_master_type_rel', 'multiple_depth'))
		{
            # First, create columns in relationship table
            $query = "ALTER TABLE `#__focus_area_publication_master_type_rel` ADD COLUMN mandatory_depth int(11) DEFAULT NULL, ADD COLUMN multiple_depth int(11) DEFAULT NULL";
			$this->db->setQuery($query);
			$this->db->query();

            # Second, copy data over from focus areas to relationship table
            $query = "UPDATE `#__focus_area_publication_master_type_rel` R, `#__focus_areas` F 
                      SET R.mandatory_depth = F.mandatory_depth, R.multiple_depth = F.multiple_depth
                      WHERE R.focus_area_id = F.id";
			$this->db->setQuery($query);
			$this->db->query();
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
		if ($this->db->tableExists('#__focus_areas') && 
            $this->db->tableExists('#__focus_area_publication_master_type_rel') &&
            $this->db->tableHasField('#__focus_area_publication_master_type_rel', 'mandatory_depth') &&
            $this->db->tableHasField('#__focus_area_publication_master_type_rel', 'multiple_depth') &&
            !$this->db->tableHasField('#__focus_areas', 'mandatory_depth') &&
            !$this->db->tableHasField('#__focus_areas', 'multiple_depth'))
        {
            # Drop columns in relationship table
            $query = "ALTER TABLE `#__focus_area_publication_master_type_rel` DROP COLUMN `mandatory_depth`, DROP COLUMN `multiple_depth`";
			$this->db->setQuery($query);
			$this->db->query();                
        }
	}
}
