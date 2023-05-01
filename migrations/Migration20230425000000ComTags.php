<?php

use Hubzero\Content\Migration\Base;
use \Components\Tags\Models\Tag;
include_once(Component::path('com_tags') . '/models/tag.php');

/**
 * Migration script converting all tags associated with focus areas to admin.
 **/
class Migration20230425000000ComTags extends Base
{
	/**
	 * Up
	 **/
	public function up()
	{
        if ($this->db->tableExists('#__focus_areas'))
        {
            // Get focus areas with tags that are not admin
            $query = "SELECT FA.*, T.admin FROM `#__focus_areas` FA
                INNER JOIN `#__tags` T
                ON FA.tag_id = T.id
                WHERE FA.parent IS NULL";

            $this->db->setQuery($query);
            $focus_areas = $this->db->loadObjectList();

            foreach ($focus_areas as $fa) {
                $this->log("Root focus area: $fa->label ($fa->id)");
                if ($fa->admin == 0) {
                    $this->create_admin_tag($fa, $fa);
                }
                $this->modify_children($fa, $fa);
            }
        }
	}

	/**
	 * Down
	 **/
	public function down()
	{
    }

    // Recursively modify children
    public function modify_children($fa, $root) {
        // Get children
        $query = "SELECT FA.*, T.admin FROM `#__focus_areas` FA
            INNER JOIN `#__tags` T
            ON FA.tag_id = T.id
            WHERE FA.parent = $fa->id";

        $this->db->setQuery($query);
        $children = $this->db->loadObjectList();

        foreach ($children as $child) {
            $this->log("  Child focus area: $child->label ($child->id)");
            if ($child->admin == 0) {
                $this->create_admin_tag($child, $root);
            }
            $this->modify_children($child, $root);
        }
    }

    public function create_admin_tag($fa, $root)
    {
        // Create new tag
        $row = Tag::oneOrNew(0)->set("raw_tag", "qfa_" . $fa->id);
        $row->set("tag", $row->normalize($row->get("raw_tag")));
        $row->set("admin", 1);
        if ($row->save()) {
            $this->log("      Successfully created admin tag with id " . $row->get('id') . " for focus area with id " . $fa->id, "success");
        } else {
            $this->log("      Unable to create admin tag for focus area " . $fa->id, "error");
            return 0;
        }

        // Update tag id for focus area
        $query = "UPDATE `#__focus_areas` SET tag_id = " . $row->get('id') . " WHERE id = " . $fa->id;
        $this->db->setQuery($query);
        if ($this->db->query()) {
            $this->log("      Successfully updated tag information for focus area", "success");
        } else {
            $this->log("      Unable to update tag information for focus area", "error");
            return 0;
        }

        // Update all versions that have the old tag id and alignment is set to focus area
        $query = "UPDATE `#__tags_object` AS O
                SET O.tagid = " . $row->get('id') . "
                WHERE O.tbl='publications' AND O.id IN (
                    SELECT * FROM (SELECT DISTINCT O2.id FROM `#__tags_object` O2
                    INNER JOIN `#__publication_versions` V
                    ON V.id = O2.objectid
                    INNER JOIN `#__publications` P
                    ON V.publication_id = P.id
                    INNER JOIN `#__focus_areas_object` FAO
                    ON FAO.objectid = P.master_type
                    WHERE O2.tbl='publications' AND O2.tagid = $fa->tag_id AND FAO.faid = $root->id) tblTmp)";
        $this->db->setQuery($query);
        if ($this->db->query()) {
            $this->log("      Successfully updated tag information for pubs", "success");
        } else {
            $this->log("      Unable to update tag information for pubs", "error");
            return 0;
        }

        return $row->get("id");
    }
}