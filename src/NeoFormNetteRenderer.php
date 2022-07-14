<?php

namespace Efabrica\NeoForms;

use Latte\Engine;
use Nette\Forms\Form;
use Nette\Forms\FormRenderer;

class NeoFormNetteRenderer implements FormRenderer
{
    private NeoFormRenderer $renderer;

    public function init(Engine $engine): void
    {
        $this->renderer = $engine->getProviders()['neoFormRenderer'];
    }

    public function render(Form $form): string
    {
        return $this->renderer->formStart($form).$this->renderer->formEnd($form);
    }
}
