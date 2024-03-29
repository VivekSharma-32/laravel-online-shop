<?php

namespace App\Http\Controllers\admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\TempImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class CategoryController extends Controller
{
    // This method will list down all the categories in the categories page 
    public function index(Request $request)
    {

        $categories = Category::latest();

        if (!empty($request->get('keyword'))) {
            $categories = $categories->where('name', 'like', '%' . $request->get('keyword') . '%');
        }


        $categories = $categories->paginate(10);

        // $data['categories'] =  $categories;

        return view('admin.category.list', compact('categories'));
    }

    public function create()
    {
        return view('admin.category.create');
    }

    // This method will store the data in the database 
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'slug' => 'required|unique:categories'
        ]);

        if ($validator->passes()) {
            $category = new Category();
            $category->name = $request->name;
            $category->slug = $request->slug;
            $category->status = $request->status;
            $category->showHome = $request->showHome;
            $category->save();

            // save image here 
            if (!empty($request->image_id)) {
                $tempImage = TempImage::find($request->image_id);

                $extArray = explode('.', $tempImage->name);
                $ext = last($extArray);

                $newImageName = $category->id . '.' . $ext;
                $sPath = public_path() . '/temp/' . $tempImage->name;
                $dPath = public_path() . '/uploads/category/' . $newImageName;
                File::copy($sPath, $dPath);

                // Generate image thumbnail 
                $destPath = public_path() . '/uploads/category/thumb/' . $newImageName;

                // create new image instance (800 x 600)
                $manager = new ImageManager(Driver::class);
                $image = $manager->read($dPath);

                // crop the best fitting 5:3 (600x360) ratio and resize to 600x360 pixel
                $image->cover(450, 600);
                $image->toPng()->save($destPath);

                $category->image = $newImageName;
                $category->save();
            }

            session()->flash('success', 'Category added successfully');

            return response()->json([
                'status' => true,
                'message' => 'Category added successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    // This method will show the edit form 
    public function edit($categoryId, Request $request)
    {
        $category = Category::find($categoryId);
        if (empty($category)) {
            return redirect()->route('categories.index');
        }

        return view('admin.category.edit', compact('category'));
    }

    // This method will update the data in the database 
    public function update($categoryId, Request $request)
    {
        $category = Category::find($categoryId);
        if (empty($category)) {

            session()->flash('error', 'Category not found.');
            return response()->json([
                'status' => false,
                'notFound' => true,
                'message' => 'category not found'
            ]);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'slug' => 'required|unique:categories,slug,' . $category->id . ',id'
        ]);

        if ($validator->passes()) {
            $category->name = $request->name;
            $category->slug = $request->slug;
            $category->status = $request->status;
            $category->showHome = $request->showHome;
            $category->save();

            $oldImage = $category->image;
            // save image here 
            if (!empty($request->image_id)) {
                $tempImage = TempImage::find($request->image_id);

                $extArray = explode('.', $tempImage->name);
                $ext = last($extArray);

                $newImageName = $category->id . '-' . time() . '.' . $ext;
                $sPath = public_path() . '/temp/' . $tempImage->name;
                $dPath = public_path() . '/uploads/category/' . $newImageName;
                File::copy($sPath, $dPath);

                // Generate image thumbnail 
                $destPath = public_path() . '/uploads/category/thumb/' . $newImageName;

                // create new image instance (800 x 600)
                $manager = new ImageManager(Driver::class);
                $image = $manager->read($dPath);

                // crop the best fitting 5:3 (600x360) ratio and resize to 600x360 pixel
                $image->cover(450, 600);
                $image->toPng()->save($destPath);

                // Delete Old Profile Pic
                File::delete(public_path('/uploads/category/thumb/' . $oldImage));
                File::delete(public_path('/uploads/category/' . $oldImage));


                $category->image = $newImageName;
                $category->save();
            }

            // $request->session()->flash('status', 'Task was successful!');

            session()->flash('success', 'Category updated successfully');

            return response()->json([
                'status' => true,
                'message' => 'Category updated successfully'
            ]);
        } else {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors()
            ]);
        }
    }

    // This method will delete the category from the database 
    public function destroy($categoryId, Request $request)
    {
        $category = Category::find($categoryId);
        if (empty($category)) {
            // return redirect()->route('categories.index');
            return response()->json([
                'status' => true,
                'message' => 'Category not found.'
            ]);
        }


        // Delete Old Profile Pic
        File::delete(public_path('/uploads/category/thumb/' . $category->image));
        File::delete(public_path('/uploads/category/' . $category->image));

        $category->delete();

        session()->flash('success', 'Category deleted successfully');
        return response()->json([
            'status' => true,
            'message' => 'Category deleted successfully.'
        ]);
    }
}
