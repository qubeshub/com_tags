<?php

use Hubzero\Content\Migration\Base;
use \Components\Tags\Models\Tag;
include_once(Component::path('com_tags') . '/models/tag.php');

/**
 * Migration script cleaning up from previous migration in date class
 **/
class Migration20220608000005ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        // Delete table jos_focus_area_publication_master_type_rel
        $tbl = '#__focus_area_publication_master_type_rel';
        if ($this->db->tableExists($tbl)) {
            $query = "DROP TABLE `$tbl`";
            $this->db->setQuery($query);
            if ($this->db->query()) {
                $this->log("Successfully dropped table " . $tbl, "success");
            } else {
                $this->log("Unable to drop table " . $tbl, "failure");
            }
        }

        // Remove mandatory_depth and multiple_depth from jos_focus_areas table
        $tbl = '#__focus_areas';
        if ($this->db->tableExists($tbl))
		{
            $fields = array('mandatory_depth', 'multiple_depth');
            foreach ($fields as $field) {
                if ($this->db->tableHasField($tbl, $field)) {
                    $query = "ALTER TABLE `$tbl` DROP COLUMN `$field`";
                    $this->db->setQuery($query);
                    $this->db->query();
                }
            }
		}
	}

	/**
	 * Down
	 **/
	public function down()
	{
        // One way change
    }
}
