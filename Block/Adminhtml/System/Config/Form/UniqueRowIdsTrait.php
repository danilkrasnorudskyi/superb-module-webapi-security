<?php

namespace Superb\WebapiSecurity\Block\Adminhtml\System\Config\Form;

/**
 * Prefix stored row keys with the field html id.
 *
 * The dynamic-rows JS builds element ids from the row key ("tr-<key>", "<key>_<column>") and looks them up
 * by id, so two fields on the same page sharing a key (e.g. "row_1" written by migrate-config) make
 * Delete remove the other field's row and pre-filled values land in the other field's inputs.
 * The prefixed keys are posted back and stored on the next save.
 */
trait UniqueRowIdsTrait
{
    private $rowIdsPrefixed = false;

    public function getArrayRows()
    {
        $element = $this->getElement();
        if (!$this->rowIdsPrefixed && $element && is_array($element->getValue())) {
            $this->rowIdsPrefixed = true;
            $prefix = $element->getHtmlId() . '_';
            $rows = [];
            foreach ($element->getValue() as $rowId => $row) {
                $rowId = (string)$rowId;
                $rows[strpos($rowId, $prefix) === 0 ? $rowId : $prefix . $rowId] = $row;
            }
            $element->setValue($rows);
        }
        return parent::getArrayRows();
    }
}
