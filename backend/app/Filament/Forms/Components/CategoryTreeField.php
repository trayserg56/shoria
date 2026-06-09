<?php

namespace App\Filament\Forms\Components;

use App\Models\Category;
use Filament\Forms\Components\Field;

class CategoryTreeField extends Field
{
    protected string $view = 'filament.forms.components.category-tree-field';

    /**
     * @return array<int, array{id: int, name: string, parent_id: int|null, children: array}>
     */
    public function getTree(): array
    {
        $categories = Category::query()
            ->select(['id', 'name', 'parent_id'])
            ->orderBy('parent_id')
            ->orderBy('name')
            ->get();

        $map = [];
        foreach ($categories as $cat) {
            $map[$cat->id] = ['id' => $cat->id, 'name' => $cat->name, 'parent_id' => $cat->parent_id, 'children' => []];
        }

        $roots = [];
        foreach ($map as $id => &$node) {
            if ($node['parent_id'] && isset($map[$node['parent_id']])) {
                $map[$node['parent_id']]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }

        return $roots;
    }
}
