<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;

/**
 * Визуальный пикер варианта — карточки с SVG-превью.
 *
 * Использование:
 *   VariantPicker::make('header.variant')
 *       ->label('Вариант шапки')
 *       ->variants([
 *           'classic' => ['label' => 'Классик', 'description' => '...', 'preview' => 'header-classic'],
 *           ...
 *       ])
 */
class VariantPicker extends Field
{
    protected string $view = 'filament.forms.components.variant-picker';

    /** @var array<string, array{label: string, description?: string, preview: string}> */
    protected array $variants = [];

    /**
     * @param  array<string, array{label: string, description?: string, preview: string}>  $variants
     */
    public function variants(array $variants): static
    {
        $this->variants = $variants;

        return $this;
    }

    /**
     * @return array<string, array{label: string, description?: string, preview: string}>
     */
    public function getVariants(): array
    {
        return $this->variants;
    }
}
