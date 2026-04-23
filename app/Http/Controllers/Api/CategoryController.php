<?php

namespace App\Http\Controllers\Api;

use App\Events\MasterDataUpdated;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        return response()->json(['success' => true, 'data' => Category::withCount('items')->orderBy('name')->get()]);
    }

    public function store(Request $request)
    {
        $v = $request->validate(['name' => 'required|string', 'code' => 'required|string|unique:categories', 'description' => 'nullable|string']);
        $cat = Category::create($v);
        broadcast(new MasterDataUpdated('kategori', 'created', $cat->id))->toOthers();
        return response()->json(['success' => true, 'data' => $cat], 201);
    }

    public function show(Category $category)
    {
        return response()->json(['success' => true, 'data' => $category->load('items')]);
    }

    public function update(Request $request, Category $category)
    {
        $v = $request->validate(['name' => 'sometimes|string', 'code' => "sometimes|string|unique:categories,code,{$category->id}", 'description' => 'nullable|string']);
        $category->update($v);
        broadcast(new MasterDataUpdated('kategori', 'updated', $category->id))->toOthers();
        return response()->json(['success' => true, 'data' => $category]);
    }

    public function destroy(Category $category)
    {
        $category->delete();
        broadcast(new MasterDataUpdated('kategori', 'deleted'))->toOthers();
        return response()->json(['success' => true, 'message' => 'Kategori dihapus']);
    }
}