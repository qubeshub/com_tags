<?php

use Hubzero\Content\Migration\Base;
use \Components\Tags\Models\Tag;
include_once(Component::path('com_tags') . '/models/tag.php');

/**
 * Migration script copying children to focus area table.
 **/
class Migration20220608000004ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        // Create focus areas object table
        if (!$this->db->tableExists('#__focus_areas_object'))
        {
            $query = "CREATE TABLE IF NOT EXISTS `#__focus_areas_object` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `objectid` int(11) unsigned NOT NULL DEFAULT '0',
                `faid` int(11) unsigned NOT NULL DEFAULT '0',
                `tbl` varchar(100) NOT NULL DEFAULT '',
                `mandatory_depth` int(11) DEFAULT NULL,
                `multiple_depth` int(11) DEFAULT NULL,
                `ordering` int(11) DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `unique_focusarea_per_obj` (`objectid`,`faid`,`tbl`),
                KEY `idx_objectid_tbl` (`objectid`,`tbl`),
                KEY `idx_faid` (`faid`)
              ) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
            $this->db->setQuery($query);
			if ($this->db->query()) {
                $this->log("Successfully created jos_focus_areas_object table", "success");
            } else {
                $this->log("Unable to create jos_focus_areas_object table", "failure");
            }
        }

        // Move content over
        if ($this->db->tableExists('#__focus_area_publication_master_type_rel')) {
            $query = "INSERT INTO `#__focus_areas_object`
                (objectid, faid, tbl, mandatory_depth, multiple_depth, ordering)
                SELECT FP.master_type_id AS objectid, 
                    FP.focus_area_id AS faid, 
                    'publication_master_types' AS tbl, 
                    FP.mandatory_depth, 
                    FP.multiple_depth, 
                    NULL AS ordering 
                FROM `#__focus_areas`
                INNER JOIN `#__focus_area_publication_master_type_rel` FP
                ON jos_focus_areas.id = FP.focus_area_id";
            $this->db->setQuery($query);
            if ($this->db->query()) {
                $this->log("Successfully copied items to jos_focus_areas_object table", "success");
            } else {
                $this->log("Unable to copy items to jos_focus_areas_object table", "failure");
            }
        }

        // Set ordering
        $query = "SELECT DISTINCT objectid FROM `#__focus_areas_object`";
        $this->db->setQuery($query);
        if ($master_types = $this->db->loadColumn()) {
            foreach($master_types as $mt) {
                $query = "UPDATE `#__focus_areas_object` AS A
                    INNER JOIN
                    (SELECT (@cnt := @cnt + 1) AS ordering, B.faid FROM
                    (SELECT faid FROM `#__focus_areas_object`
                    WHERE objectid = " . $mt . 
                    " ORDER BY faid) B
                    CROSS JOIN (SELECT @cnt := 0) AS dummy) C
                    ON A.faid = C.faid
                    SET A.ordering = C.ordering
                    WHERE A.objectid = " . $mt;
                $this->db->setQuery($query);
                if ($this->db->query()) {
                    $this->log("Successfully updated ordering for master type " . $mt, "success");
                } else {
                    $this->log("Unable to update ordering for master type " . $mt, "failure");
                }
            }
        }

        // Delete tables (once code works)
	}

	/**
	 * Down
	 **/
	public function down()
	{
        // One way change
    }
}
