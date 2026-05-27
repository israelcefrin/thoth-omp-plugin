<?php

/**
 * @file plugins/generic/thoth/classes/hooks/PublicationFormatFormHandler.php
 *
 * Copyright (c) 2024-2026 Lepidus Tecnologia
 * Copyright (c) 2024-2026 Thoth
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationFormatFormHandler
 *
 * @ingroup plugins_generic_thoth
 *
 * @brief Handles Thoth accessibility metadata in OMP publication format forms
 */

namespace APP\plugins\generic\thoth\classes\hooks;

use APP\plugins\generic\thoth\classes\templateFilters\PublicationFormatTemplateFilter;
use APP\template\TemplateManager;
use PKP\plugins\GenericPlugin;

class PublicationFormatFormHandler
{
    public const ACCESSIBILITY_FIELDS = [
        'accessibilityApplicable',
        'accessibilityException',
        'accessibilityStandards',
        'accessibilityReportUrl',
        'hasAltTextAllImages',
        'pdfIsTagged',
        'accessibilityComplianceLevel',
        'accessibilityStatementPresent',
        'knownLimitations',
    ];

    private GenericPlugin $plugin;

    public function __construct(GenericPlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function addAccessibilityFields($hookName, $args): bool
    {
        $form = $args[0];
        $templateMgr = TemplateManager::getManager();
        $publicationFormat = $form->getPublicationFormat();

        foreach (self::ACCESSIBILITY_FIELDS as $fieldName) {
            if ($publicationFormat && $form->getData($fieldName) === null) {
                $value = $publicationFormat->getData($fieldName);
                if ($fieldName === 'accessibilityStandards') {
                    $value = $this->normalizeStandardsForForm($value);
                } elseif (in_array($fieldName, $this->getBooleanFieldNames(), true)) {
                    $value = $this->normalizeBooleanValue($value);
                }

                $form->setData($fieldName, $value);
            }
        }

        $templateMgr->assign([
            'thothAccessibilityApplicabilityOptions' => $this->getAccessibilityApplicabilityOptions(),
            'thothAccessibilityExceptionOptions' => $this->getAccessibilityExceptionOptions(),
            'thothAccessibilityStandardOptions' => $this->getAccessibilityStandardOptions(),
            'thothAccessibilityComplianceLevelOptions' => $this->getAccessibilityComplianceLevelOptions(),
        ]);

        (new PublicationFormatTemplateFilter($this->plugin))->register($templateMgr);

        return false;
    }

    public function addAccessibilityFieldNames($hookName, $dao, &$fieldNames): bool
    {
        $fieldNames = array_values(array_unique(array_merge($fieldNames, self::ACCESSIBILITY_FIELDS)));

        return false;
    }

    public function addAccessibilityUserVars($hookName, $args): bool
    {
        $vars = & $args[1];
        $vars = array_unique(array_merge($vars, self::ACCESSIBILITY_FIELDS));

        return false;
    }

    public function validateAccessibilityFields($hookName, $args): bool
    {
        $form = $args[0];
        $reportUrl = trim((string) $form->getData('accessibilityReportUrl'));

        if ($reportUrl !== '' && filter_var($reportUrl, FILTER_VALIDATE_URL) === false) {
            $form->addError(
                'accessibilityReportUrl',
                __('plugins.generic.thoth.publicationFormat.accessibilityReportUrl.invalid')
            );
        }

        return false;
    }

    public function saveAccessibilityFields($hookName, $args): bool
    {
        $form = $args[0];
        $publicationFormat = $form->getPublicationFormat();

        if (!$publicationFormat) {
            return false;
        }

        foreach (self::ACCESSIBILITY_FIELDS as $fieldName) {
            $publicationFormat->setData($fieldName, $this->normalizeFieldValue($fieldName, $form->getData($fieldName)));
        }

        return false;
    }

    private function normalizeFieldValue(string $fieldName, $value)
    {
        if ($fieldName === 'accessibilityStandards') {
            return $this->normalizeStandardsForStorage($value);
        }

        if (in_array($fieldName, $this->getBooleanFieldNames(), true)) {
            return $this->normalizeBooleanValue($value);
        }

        return $this->normalizeOptionalValue($value);
    }

    private function normalizeOptionalValue($value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function normalizeBooleanValue($value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    private function normalizeStandardsForForm($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decodedValue = json_decode($value, true);
            if (is_array($decodedValue)) {
                return $decodedValue;
            }
        }

        return [];
    }

    private function normalizeStandardsForStorage($value): ?string
    {
        if (!is_array($value)) {
            return null;
        }

        $trimmedValues = array_map('trim', $value);
        $filteredValues = array_filter($trimmedValues, fn ($standard) => $standard !== '');
        $normalizedValues = array_values($filteredValues);

        if ($normalizedValues === []) {
            return null;
        }

        return json_encode($normalizedValues);
    }

    private function getBooleanFieldNames(): array
    {
        return [
            'accessibilityApplicable',
            'hasAltTextAllImages',
            'pdfIsTagged',
            'accessibilityStatementPresent',
        ];
    }

    private function getAccessibilityApplicabilityOptions(): array
    {
        return [
            '1' => 'Applicable',
            '0' => 'Not applicable',
        ];
    }

    private function getAccessibilityStandardOptions(): array
    {
        return [
            'wcag-2.1-AA' => 'WCAG 2.1 AA',
            'wcag-2.2-AA' => 'WCAG 2.2 AA',
            'pdfua-1' => 'PDF/UA-1',
            'pdfua-2' => 'PDF/UA-2',
        ];
    }

    private function getAccessibilityExceptionOptions(): array
    {
        return [
            '' => 'None',
            'small' => 'Small publisher exemption',
            'legacy' => 'Legacy content',
            'other' => 'Other',
        ];
    }

    private function getAccessibilityComplianceLevelOptions(): array
    {
        return [
            '' => 'None',
            'wcag-2.0-A' => 'WCAG 2.0 A',
            'wcag-2.0-AA' => 'WCAG 2.0 AA',
            'wcag-2.1-AA' => 'WCAG 2.1 AA',
            'wcag-2.2-AA' => 'WCAG 2.2 AA',
        ];
    }
}
