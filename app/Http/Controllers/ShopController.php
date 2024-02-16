<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRating;
use App\Models\SubCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ShopController extends Controller
{
    public function index(Request $request, $categorySlug = null, $subCategorySlug = null)
    {
        $categorySelected = "";
        $subCategorySelected = "";
        $brandsArray = [];


        $categories = Category::orderBy('name', 'ASC')
            ->with('sub_category')
            ->where('status', 1)
            ->get();

        $brands = Brand::orderBy('name', 'ASC')
            ->where('status', 1)
            ->get();

        $products = Product::where('status', 1);
        // Apply filters for category 
        if (!empty($categorySlug)) {
            $category = Category::where('slug', $categorySlug)->first();
            $products = $products->where('category_id', $category->id);
            $categorySelected = $category->id;
        }
        // Apply filters for subcategory 
        if (!empty($subCategorySlug)) {
            $subCategory = SubCategory::where('slug', $subCategorySlug)->first();
            $products = $products->where('sub_category_id', $subCategory->id);
            $subCategorySelected = $subCategory->id;
        }

        if (!empty($request->get('brand'))) {
            $brandsArray = explode(',', $request->get('brand'));
            $products = $products->whereIn('brand_id', $brandsArray);
        }

        if ($request->get('price_max') != '' && $request->get('price_min') != '') {
            if ($request->get('price_max') == 1000) {
                $products = $products->whereBetween('price', [intVal($request->get('price_min')), 1000000]);
            } else {
                $products = $products->whereBetween('price', [intVal($request->get('price_min')), intVal($request->get('price_max'))]);
            }
        }

        if (!empty($request->get('search'))) {
            $products = $products->where('title', 'like', '%' . $request->get('search') . '%');
        }

        // Sorting on dropdown 
        if ($request->get('sort') != '') {
            if ($request->get('sort') == 'latest') {
                $products = $products->orderBy('price', 'DESC');
            } else if ($request->get('sort') == 'price_asc') {
                $products = $products->orderBy('price', 'ASC');
            } else {
                $products = $products->orderBy('price', 'DESC');
            }
        } else {
            $products = $products->orderBy('price', 'DESC');
        }

        $products = $products->paginate(6);

        $data['categories'] = $categories;
        $data['brands'] = $brands;
        $data['products'] = $products;
        $data['categorySelected'] = $categorySelected;
        $data['subCategorySelected'] = $subCategorySelected;
        $data['brandsArray'] = $brandsArray;
        $data['priceMax'] = (intVal($request->get('price_max') == 0 ? 1000 : $request->get('price_max')));
        $data['priceMin'] = intVal($request->get('price_min'));
        $data['sort'] = $request->get('sort');


        return view('front.shop', $data);
    }

    public function product($slug)
    {
        $product = Product::where('slug', $slug)
            ->withCount('product_ratings')
            ->withSum('product_ratings', 'rating')
            ->with(['product_images', 'product_ratings'])
            ->first();


        if ($product == null) {
            abort(404);
        }

        // fetch related products 
        $relatedProducts = [];

        if ($product->related_products != '') {
            $productArray = explode(',', $product->related_products);

            $relatedProducts = Product::whereIn('id', $productArray)
                ->where('status', 1)
                ->with('product_images')
                ->get();
        }

        $data['product'] = $product;
        $data['relatedProducts'] = $relatedProducts;

        // Rating Calculation 
        $avgRating = '0.00';
        $avgRatingPercentage = 0;
        if ($product->product_ratings_count > 0) {
            $avgRating = number_format(($product->product_ratings_sum_rating / $product->product_ratings_count), 2);
            $avgRatingPercentage = ($avgRating * 100) / 5;
        }

        $data['avgRating'] = $avgRating;
        $data['avgRatingPercentage'] = $avgRatingPercentage;

        return view('front.product', $data);
    }

    public function saveRating($id, Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|min:5',
            'email' => 'required|email',
            'comment' => 'required|min:10',
            'rating' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }


        $count = ProductRating::where('email', $request->email)->count();

        if ($count > 0) {
            session()->flash('error', 'You have already rated this product');
            return response()->json([
                'status' => true,
            ]);
        }

        $productRating = new ProductRating;
        $productRating->product_id = $id;
        $productRating->username = $request->name;
        $productRating->email = $request->email;
        $productRating->comment = $request->comment;
        $productRating->rating = $request->rating;
        $productRating->status = 0;
        $productRating->save();

        session()->flash('success', 'Thanks for your ratings!');
        return response()->json([
            'status' => true,
            'message' => 'Thanks for your ratings!',
        ]);
    }
}
