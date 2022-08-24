<?php

use Hubzero\Content\Migration\Base;
use \Components\Tags\Models\Tag;
include_once(Component::path('com_tags') . '/models/tag.php');

/**
 * Migration script copying children to focus area table.
 **/
class Migration20220608000003ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__focus_areas'))
        {
            // First, add parent and ordering fields
            if (!$this->db->tableHasField('#__focus_areas', 'parent') &&
                !$this->db->tableHasField('#__focus_areas', 'ordering'))
            {
                $query = "ALTER TABLE `#__focus_areas` ADD COLUMN ordering int(10) unsigned DEFAULT NULL AFTER about, ADD COLUMN parent int(10) unsigned DEFAULT NULL AFTER about";
                $this->db->setQuery($query);
                $this->db->query();
            }

            // Second, add children to focus areas

            // Get focus areas
            $query = "SELECT id, tag_id FROM `#__focus_areas`";
            $this->db->setQuery($query);
            $focus_areas = $this->db->loadObjectList();

            foreach ($focus_areas as $fa) {
                $this->log("FA: " . $fa->tag_id);

                // Get children/ontology
                $query = "SELECT C.*, P.tagid AS parent_id FROM `#__tags_object` P
                INNER JOIN
                (SELECT T.id AS tag_id, T.raw_tag AS label, T.description as about, O.ordering from `#__tags_object` O
                INNER JOIN `#__tags` T
                ON T.id = O.objectid
                WHERE O.label IN ('label', 'parent') AND O.tagid = " . $this->db->quote($fa->tag_id) . "
                ORDER BY T.id) C
                ON C.tag_id = P.objectid
                WHERE P.label = 'parent'";
                $this->db->setQuery($query);
                $ontology = $this->db->loadObjectList();
                foreach ($ontology as $child) {
                    $this->log("  " . $child->label);
                    // Add to focus area with 0 as tagid for now
                    $query = "INSERT INTO `#__focus_areas` (tag_id, label, about, ordering, parent) VALUES 
                                (0, " . 
                                $this->db->quote($child->label) . ", " .
                                $this->db->quote($child->about) . ", " .
                                (!is_null($child->ordering) ? $child->ordering : 'NULL') . ", " . 
                                $child->parent_id .
                                ")";
                    $this->db->setQuery($query);
                    $this->db->query();
                    
                    // Get newly created focus area id
                    $this->db->setQuery("SELECT id FROM `#__focus_areas` WHERE tag_id = 0");
                    if ($fa_id = $this->db->loadResult()) {
                        $this->log("    Created new focus area with id " . $fa_id, "success");
                    } else {
                        $this->log("    Unable to create new focus area", "error");
                        exit;
                    }

                    // Check if tag is already in focus area table
                    $this->db->setQuery("SELECT COUNT(*) FROM `#__focus_areas` WHERE tag_id = " . $child->tag_id);
                    if ($this->db->loadResult()) {
                        $this->log("    Focus area with tag_id " . $child->tag_id . " already exists! Duplicating...", "warning");
                        if ($tag_id = $this->duplicate_child($child, $fa_id)) {
                            $this->log("    ...success!", "success");
                            $child->tag_id = $tag_id; // Update for changing parent later
                        } else {
                            $this->log("    ...oops!", "error");
                            exit;
                        }
                    } else {
                        $tag_id = $child->tag_id;
                    }

                    // Update tag id information for focus area
                    $this->db->setQuery("UPDATE `#__focus_areas` SET tag_id = " . $tag_id . " WHERE id = " . $fa_id);
                    if ($this->db->query()) {
                        $this->log("    Successfully updated tag " . $tag_id . " info for focus area " . $fa_id, "success");
                    } else {
                        $this->log("    Unable to update tag " . $tag_id . " info for focus area " . $fa_id, "error");
                    }
                }
            }

            // Update parent from tag_id to fa_id
            $this->db->setQuery("UPDATE `#__focus_areas` AS FA1
                                INNER JOIN `#__focus_areas` AS FA2
                                ON FA1.tag_id = FA2.parent
                                SET FA2.parent = FA1.id");
            if ($this->db->query()) {
                $this->log("Successfully changed parent info to fa id", "success");
            } else {
                $this->log("Unable to change parent info to fa id", "error");
            }

            // Update CourseSource tables
            $tables = array("course", "framework", "topic", "goal", "taxonomy");
            foreach($tables as $table) {
                if ($this->update_names_from_table("sg_coursesource", $table)) {
                    $this->log("Successfully updated table " . $table . " from database sg_coursesource", "success");
                } else {
                    exit;
                }
            }

            // Update SIMIODE taxonomy
            if ($this->update_names_from_table("sg_simiode", "taxonomy")) {
                $this->log("Successfully updated taxonomy table from database sg_simiode", "success");
            } else {
                exit;
            }
        }
	}

	/**
	 * Down
	 **/
	public function down()
	{
        if ($this->db->tableExists('#__focus_areas') && 
            $this->db->tableHasField('#__focus_areas', 'parent') &&
            $this->db->tableHasField('#__focus_areas', 'ordering'))
		{
			// $query = "ALTER TABLE `#__focus_areas` DROP COLUMN ordering, DROP COLUMN parent";
			// $this->db->setQuery($query);
			// $this->db->query();
		}
    }

    public function duplicate_child($child, $fa_id)
    {
        // Create new tag
        $row = Tag::oneOrNew(0)->set("raw_tag", "qfa_" . $fa_id);
        $row->set("tag", $row->normalize($row->get("raw_tag")));
        $row->set("admin", 1);
        if ($row->save()) {
            $this->log("      Successfully created tag with id " . $row->get('id') . " for child", "success");
        } else {
            $this->log("      Unable to create tag for child", "error");
            return 0;
        }

        // Update all versions that have child and parent tags
        $query = "UPDATE `#__tags_object` AS OP
                INNER JOIN `#__tags_object` AS OC
                ON OP.objectid = OC.objectid
                SET OC.tagid = " . $row->get('id') . "
                WHERE OP.tbl='publications' AND OP.tagid = " . $child->parent_id . " AND OC.tbl='publications' AND OC.tagid = " . $child->tag_id;
        $this->log($query);
        $this->db->setQuery($query);
        if ($this->db->query()) {
            $this->log("      Successfully updated tag information for pubs", "success");
        } else {
            $this->log("      Unable to update tag information for pubs", "error");
            return 0;
        }

        return $row->get("id");
    }

    public function update_names_from_table($db, $table) {
        $sg_db = \Hubzero\User\Group\Helper::getDBO(array(), substr($db, 3));
        $table = $db . "." . $table;
        $this->db->setQuery("SELECT tag_id FROM `#__focus_areas` WHERE label LIKE '" . $table . "%'");
        $fas = $this->db->loadColumn();
        foreach ($fas as $tag_id) {
            // Get course name from database
            $sg_db->setQuery("SELECT name FROM " . $table . " WHERE tag_id = " . $tag_id);
            $name = $sg_db->loadResult();

            // Add name to database
            $this->db->setQuery("UPDATE `#__focus_areas` SET label = " . $this->db->quote($name) . " WHERE tag_id = " . $tag_id);
            if (!$this->db->query()) {
                $this->log("Error in changing focus area label with tag id " . $tag_id . " to " . $name, "error");
                return 0;
            }   
        }

        return 1;
    }
}
