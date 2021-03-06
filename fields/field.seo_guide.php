<?php
Class fieldSeo_guide extends Field
{
    public function __construct()
    {
		parent::__construct();
        $this->_name = __('SEO Guide');
        $this->set('location', 'sidebar');
    }

    public function displaySettingsPanel(&$wrapper, $errors = null)
    {
		parent::displaySettingsPanel($wrapper, $errors);
        $div = new XMLElement('div', NULL, array('class' => 'group'));

        // $label = Widget::Label(__('Fields'));
        $label = new XMLElement('div', __('Fields'));
        $section_id = $this->get('parent_section');
        if ($section_id == false) {
            $label->appendChild(new XMLElement('p', __('Please save the section first, then choose from a list of fields'), array('class' => 'info')));
        } else {
            $fields = FieldManager::fetch(null, $section_id);
            $usedFields = $this->getFields($section_id);
            $values = array();
            foreach ($usedFields as $usedField)
            {
                $values[$usedField['field_id']] = $usedField['priority'];
            }
            $i = 0;
            foreach ($fields as $field)
            {
                if ($field->get('type') != 'seo_guide') {

                    $i++;

                    $row = new XMLElement('div', '', array('style' => 'margin-bottom: 0; clear: both; height: 22px;'));
                    $attr = array('style' => 'position: relative; top: 2px; margin-top: 2px; margin-left: 2px;');

                    if (array_key_exists($field->get('id'), $values)) {
                        $attr['checked'] = 'checked';
                        $p = $values[$field->get('id')];
                    } else {
                        $p = false;
                    }

                    $row->appendChild(Widget::Input('fields[' . $this->get('sortorder') . '][fields][]', $field->get('id'), 'checkbox', $attr));
                    $row->setValue($field->get('label'), false);

                    $priority = array(
                        array(1, $p == 1, __('1 - Low priority')),
                        array(2, $p == 2, __('2')),
                        array(3, ($p == 3 || $p == false), __('3 - Normal priority')),
                        array(4, $p == 4, __('4')),
                        array(5, $p == 5, __('5 - High priority'))
                    );

                    $select = Widget::Select('fields[' . $this->get('sortorder') . '][priority][]', $priority, array('style' => 'float: right; width: 150px;'));
                    $row->appendChild($select);

                    $label->appendChild($row);
                }
            }

            // $select = Widget::Select('fields[' . $this->get('sortorder') . '][fields][]', $options, array('multiple' => 'multiple'));
            // $label->appendChild($select);
        }
        $div->appendChild($label);

        $label = Widget::Label(__('Information'));
        $label->appendChild(new XMLElement('p', __('Select the fields which are used to display the content on your site. Think of fields that store stuff like the header, an introtext, the main content, description, keywords, etc...<br />You can set a priority for each field. This will be used to determine the weight of the content. For example: a header that is also used for the title of the page has more priority then a small chunk of text in the sidebar.')));
        $div->appendChild($label);

        $wrapper->appendChild($div);

		$div = new XMLElement('div', NULL, array('class' => 'group'));
		$label = Widget::Label();
		$label->setAttribute('class', 'column');
		$input = Widget::Input('fields['.$this->get('sortorder').'][ignore_h1]', 'yes', 'checkbox');
		if($this->get('ignore_h1') == 1) $input->setAttribute('checked', 'checked');
		$label->setValue(__('%s Ignore H1', array($input->generate())));
		$div->appendChild($label);

		$this->appendShowColumnCheckbox($div);

		$wrapper->appendChild($div);


    }

    public function commit()
    {
		$ok = parent::commit();
        if (!$ok) return false;

		FieldManager::saveSettings($this->get('id'), array(
			'ignore_h1' => ($this->get('ignore_h1') != false ? 1 : 0)
		));

        if ($ok != false) {
            Symphony::Database()->query("DELETE FROM `tbl_seo_guide_fields` WHERE `section_id` = " . $this->get('parent_section'));
            $priority = $this->get('priority');
            $i = 0;

			if($fields = $this->get('fields'))
			{
				foreach ($fields as $field_id)
				{
					Symphony::Database()->insert(array(
						'section_id' => $this->get('parent_section'),
						'field_id' => $field_id,
						'priority' => $priority[$i]), 'tbl_seo_guide_fields');
					$i++;
				}
			}
        }

        return $ok;
    }

    public function displayPublishPanel(&$wrapper, $data = NULL, $flagWithError = NULL, $fieldnamePrefix = NULL, $fieldnamePostfix = NULL, $entry_id = null)
    {
        $label = Widget::Label($this->get('label'));
        $fields = $this->getFields($this->get('parent_section'));

        // Fields to watch:
		$xmlData = new XMLElement('data');
        $xmlFields = new XMLElement('fields');
        foreach ($fields as $field)
        {
            $xmlFields->appendChild(new XMLElement('field', '', array('id' => $field['field_id'], 'priority' => $field['priority'])));
        }
        $xmlData->appendChild($xmlFields);

		// SEO Keywords-fields
		$seoKeywordsField = FieldManager::fetch(null, $this->get('parent_section'), 'ASC', 'sortorder', 'taglist', null, ' AND `element_name` = \'seo-keywords\'');
		$seoKeywords = new XMLElement('seo-keywords');
		if(is_array($seoKeywordsField) && count($seoKeywordsField) == 1)
		{
			$seoKeywordsField = array_shift($seoKeywordsField);
			$seoKeywordsFieldId = $seoKeywordsField->get('id');
			$seoKeywords->setAttribute('id', $seoKeywordsFieldId);
			/*$entry = EntryManager::fetch($entry_id);
			$keyWordsData = $entry[0]->getData($seoKeywordsFieldId);
			foreach($keyWordsData['value'] as $value)
			{
				$seoKeywords->appendChild(new XMLElement('keyword', $value));
			}*/
		}
		$xmlData->appendChild($seoKeywords);

		// Current value:
        $xmlData->appendChild(new XMLElement('value', $data['value']));

		// Element name:
        $xmlData->appendChild(new XMLElement('element-name', $this->get('element_name')));

		// Ignore H1:
		$xmlData->appendChild(new XMLElement('ignore-h1', ($this->get('ignore_h1') == 1 ? 'Yes' : 'No')));

        $xslt = new XsltProcess($xmlData->generate(), file_get_contents(EXTENSIONS . '/seo_guide/assets/seo_guide.xsl'));
        $label->appendChild(new XMLElement('div', $xslt->process()));

        $wrapper->appendChild($label);
    }

    public function appendFormattedElement(&$wrapper, $data, $encode = false)
    {
        $values = explode(' ', $data['value']);
        $elem   = new XMLElement($this->get('element_name'));
        foreach($values as $value)
        {
            $elem->appendChild(new XMLElement('keyword', $value));
        }
        $wrapper->appendChild($elem);
    }

    /**
     * Get the fields that are tagged for this section
     * @param $section_id   The ID of the section
     * @return array        An associated array with ID's and priority
     */
    private function getFields($section_id)
    {
        return Symphony::Database()->fetch('SELECT `field_id`, `priority` FROM `tbl_seo_guide_fields` WHERE `section_id` = ' . $section_id . ';');
    }

	public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
		if(isset($data['value']))
		{
			$val = intval($data['value']);
			if($val < 30) {
				$color = 'f00';
			} elseif($val < 40) {
				$color = 'f80';
			} elseif($val < 50) {
				$color = 'c80';
			} elseif($val < 60) {
				$color = 'ca0';
			} elseif($val < 70) {
				$color = '9c0';
			} else {
				// superb!
				$color = '2c0';
			}
			return '<span style="color: #'.$color.';">'.$val.'%</span>';
		}
		return false;
	}

}
