<?php

namespace Efabrica\NeoForms\Render;

use Efabrica\NeoForms\Control\CodeEditor;
use Efabrica\NeoForms\Control\ToggleSwitch;
use Efabrica\NeoForms\Render\Input\CustomInputRenderer;
use Nette\Forms\Controls\BaseControl;
use Nette\Forms\Controls\Button;
use Nette\Forms\Controls\Checkbox;
use Nette\Forms\Controls\CheckboxList;
use Nette\Forms\Controls\HiddenField;
use Nette\Forms\Controls\MultiSelectBox;
use Nette\Forms\Controls\RadioList;
use Nette\Forms\Controls\SelectBox;
use Nette\Forms\Controls\TextArea;
use Nette\Forms\Controls\TextBase;
use Nette\Forms\Controls\UploadControl;
use Nette\Localization\Translator;
use Nette\Utils\Html;
use RadekDostal\NetteComponents\DateTimePicker\AbstractDateTimePicker;
use RadekDostal\NetteComponents\DateTimePicker\DateTimePicker;
use RadekDostal\NetteComponents\DateTimePicker\TbDateTimePicker;

class NeoInputRenderer
{
    private NeoFormRendererTemplate $template;

    private NeoInputViewRenderer $viewRenderer;

    private Translator $translator;

    /** @var CustomInputRenderer[] */
    private array $customRenderers = [];

    public function __construct(NeoFormRendererTemplate $template, Translator $translator)
    {
        $this->template = $template;
        $this->viewRenderer = new NeoInputViewRenderer($template);
        $this->translator = $translator;
    }

    public function block(string $blockName, array $attrs): string
    {
        return $this->template->block($blockName, $attrs);
    }

    public function register(string $key, CustomInputRenderer $renderer): void
    {
        unset($this->customRenderers[$key]);
        $this->customRenderers[$key] = $renderer;
    }

    public function input(BaseControl $el, array $options = []): string
    {
        /** @var Html $control */
        $control = $el->getControl();

        if ($options['readonly'] ?? (bool)$el->getOption('readonly')) {
            foreach (array_reverse($this->customRenderers) as $renderer) {
                $result = $renderer->readonlyRender($el, $options);
                if ($result !== null) {
                    return $result;
                }
            }
            return $this->viewRenderer->input($el);
        }

        $attrs = $control->attrs;
        unset($attrs['data-nette-rules']);
        if (!($el instanceof TextBase) && is_string($attrs['placeholder'] ?? null)) {
            $attrs['placeholder'] = $this->translator->translate($attrs['placeholder']);
        }
        $attrs += array_filter($options, 'is_scalar');

        return $this->block('input', [
            'input' => Html::fromHtml($this->inputBody($el, $attrs, $options)),
            'description' => Html::fromHtml($this->description($el, $options)),
        ]);
    }

    public function textInput(BaseControl $el, array $attrs, array $options): string
    {
        return $this->block('inputText', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
        ]);
    }

    public function custom(BaseControl $el, array $attrs, array $options): string
    {
        return $this->block('customInput', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
            'customEl' => $el,
        ]);
    }

    /**
     * @param SelectBox|MultiSelectBox $el
     * @param array                    $attrs
     * @param array                    $options
     * @return string
     */
    public function selectBox($el, array $attrs, array $options): string
    {
        $prompt = $el instanceof SelectBox ? $el->getPrompt() : null;
        /** @var \Efabrica\NeoForms\Control\SelectBox $el */
        return $this->block('selectBox', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
            'prompt' => $prompt,

            /** @see \Efabrica\NeoForms\Control\SelectBoxTrait */
            'items' => $el->getItems(true),

            'selected' => $el->getValue(),
        ]);
    }

    public function radio(RadioList $el, array $attrs, array $options): string
    {
        return $this->block('radio', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
            'items' => $el->getItems(),
            'value' => $el->getValue(),
            'name' => $el->getHtmlName(),
            'required' => $el->isRequired(),
        ]);
    }

    public function button(Button $el, array $attrs, array $options): string
    {
        return $this->block('submitButton', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
            'icon' => $el->getOption('icon', 'done'),
            'caption' => $el->getCaption(),
        ]);
    }

    public function checkbox(Checkbox $checkbox, array $options): string
    {
        $caption = $options['caption'] ?? '';
        return $this->block($checkbox instanceof ToggleSwitch ? 'toggleSwitch' : 'checkbox', [
            'caption' => $caption === true ? $checkbox->getCaption() : $caption,
            'info' => $checkbox->getOption('info'),
            'labelAttrs' => array_filter($checkbox->getLabelPart()->attrs, 'is_scalar'),
            'inputAttrs' => array_filter($checkbox->getControlPart()->attrs, 'is_scalar'),
            'options' => $checkbox->getOptions() + $options,
        ]);
    }

    public function checkboxList(CheckboxList $el, array $options): string
    {
        $inputAttrs = $labelAttrs = [];
        foreach ($el->getItems() as $value => $label) {
            $inputAttrs[$value] = (array)($el->getOptions()[$value]['input'] ?? [])
                + ($options[$value]['input'] ?? [])
                + ($el->getOptions()['input'] ?? [])
                + ($options['input'] ?? [])
                + $el->getControlPart($value)->attrs;
            $inputAttrs[$value] = array_filter($inputAttrs[$value], 'is_scalar');

            $labelAttrs[$value] = (array)($el->getOptions()[$value]['label'] ?? [])
                + ($options[$value]['label'] ?? [])
                + ($el->getOptions()['label'] ?? [])
                + ($options['label'] ?? [])
                + $el->getLabelPart($value)->attrs;
            $labelAttrs[$value] = array_filter($labelAttrs[$value], 'is_scalar');
        }
        return $this->block('checkboxList', [
            'items' => $el->getItems(),
            'inputAttrs' => $inputAttrs,
            'labelAttrs' => $labelAttrs,
            'options' => $el->getOptions() + $options,
            'attrs' => array_filter($el->getOptions() + $options, 'is_scalar'),
        ]);
    }

    public function textarea(BaseControl $el, array $attrs, array $options): string
    {
        $blockName = $el instanceof CodeEditor ? 'codeEditor' : 'textarea';
        return $this->block($blockName, [
            'value' => Html::fromHtml(htmlspecialchars($el->getControl()->getText(), ENT_NOQUOTES, 'UTF-8')),
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
        ]);
    }

    public function datepicker(AbstractDateTimePicker $el, array $attrs, array $options): string
    {
        return $this->block('datepicker', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
            'isTime' => $el instanceof DateTimePicker || $el instanceof TbDateTimePicker,
        ]);
    }

    public function hidden(HiddenField $el, array $attrs, array $options): string
    {
        return $this->block('hidden', [
            'el' => $el,
            'attrs' => $attrs,
            'options' => $options,
            'errors' => $el->getErrors(),
        ]);
    }

    public function description(BaseControl $el, array $options): string
    {
        $o = $el->getOptions() + $options;
        return $this->block('description', [
            'el' => $el,
            'description' => Html::fromHtml($o['help'] ?? $o['description'] ?? ''),
        ]);
    }

    public function upload(UploadControl $el, array $attrs, array $options): string
    {
        return $this->block('upload', [
            'attrs' => $attrs,
            'options' => $el->getOptions() + $options,
        ]);
    }

    /**
     * @param BaseControl $el
     * @param array       $attrs
     * @param array       $options
     * @return string
     */
    private function inputBody(BaseControl $el, array $attrs, array $options): string
    {
        foreach (array_reverse($this->customRenderers) as $renderer) {
            $result = $renderer->render($el, $attrs, $options);
            if ($result !== null) {
                return $result;
            }
        }
        if ($el instanceof Checkbox) {
            return $this->checkbox($el, $options);
        }
        if ($el instanceof AbstractDateTimePicker) {
            return $this->datepicker($el, $attrs, $options);
        }
        if ($el instanceof SelectBox || $el instanceof MultiSelectBox) {
            return $this->selectBox($el, $attrs, $options);
        }
        if ($el instanceof Button) {
            return $this->button($el, $attrs, $options);
        }
        if ($el instanceof TextArea) {
            return $this->textarea($el, $attrs, $options);
        }
        if ($el instanceof HiddenField) {
            return $this->hidden($el, $attrs, $options);
        }
        if ($el instanceof UploadControl) {
            return $this->upload($el, $attrs, $options);
        }
        if ($el instanceof RadioList) {
            return $this->radio($el, $attrs, $options);
        }
        if ($el instanceof CheckboxList) {
            return $this->checkboxList($el, $options);
        }

        return $this->textInput($el, $attrs, $options);
    }
}
