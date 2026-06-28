<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Tables\ProductTable;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    public function index()
    {
        return Inertia::render('Products/Index', [
            'products' => (new ProductTable())->toInertia(),
        ]);
    }

    public function bulkAction(Request $request)
    {
        // Simple mock bulk action handler
        $action = $request->input('action_key');
        $ids = $request->input('ids', []);
        
        if ($action === 'delete') {
            Product::whereIn('id', $ids)->delete();
        } elseif ($action === 'mark_active') {
            Product::whereIn('id', $ids)->update(['is_active' => true]);
        } elseif ($action === 'mark_inactive') {
            Product::whereIn('id', $ids)->update(['is_active' => false]);
        }

        return redirect()->back();
    }
}
