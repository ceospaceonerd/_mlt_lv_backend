<?php

namespace App\Http\Controllers;

// use App\DataTables\CategoryDataTable;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CategoryController extends Controller
{




    // public function index(CategoryDataTable $dataTable)
    // {
    //     if (\Auth::user()->can('manage-category')) {
    //         return $dataTable->render('category.index');
    //     } else {
    //         return redirect()->back()->with('failed', __('Permission Denied.'));
    //     }
    // }

    public function create()
    {
        $data = array(
            'code'=>401,
            'status'=>'Error',
            'message'=>'Unauthorized',
            'redirect'=>'/'

        );
        if (\Auth::user()->can('create-category')) {
            $category = Category::all();
            // return view('category.create', compact('category'));
            return response()->json(['Status'=>'Success','message'=>'','category'=>$category],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json($data,$data['code']);
        }
    }

    public function store(Request $request)
    {
        $data = array(
            'code'=>401,
            'status'=>'Error',
            'message'=>'Unauthorized',
            'redirect'=>'/'

        );

        if (\Auth::user()->can('create-category')) {
            request()->validate([
                'name' => 'required',
                'status' => 'required',
            ]);
            Category::create([
                'name' => $request->name,
                'status' => $request->status
            ]);
            return redirect()->route('category.index')->with('success', __('Category Created Successfully'));
        } else {
            return response()->json($data,$data['code']);
        }
    }

    public function edit($id)
    {
        $data = array(
            'code'=>401,
            'status'=>'Error',
            'message'=>'Unauthorized',
            'redirect'=>'/'

        );
        if (\Auth::user()->can('edit-category')) {

            $category = Category::find($id);
            // return view('category.edit', compact('category'));
            return response()->json(['Status'=>'Success','message'=>'','category'=>$category],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json($data,$data['code']);
        }
    }

    public function update(Request $request, $id)
    {
        $data = array(
            'code'=>401,
            'status'=>'Error',
            'message'=>'Unauthorized',
            'redirect'=>'/'

        );
        if (\Auth::user()->can('edit-category')) {
            request()->validate([
                'name' => 'required',
                'status' => 'required',
            ]);
            $category = Category::find($id);
            $category->name = $request->name;
            $category->status = $request->status;
            $category->update();
            return response()->json(['Status'=>'Success','message'=>'Category Updated Successfully','redirect'=>'category.index'],200);
        } else {
            return response()->json($data,$data['code']);

        }
    }

    public function destroy($id)
    {
        $data = array(
            'code'=>401,
            'status'=>'Error',
            'message'=>'Unauthorized',
            'redirect'=>'/'

        );
        if (\Auth::user()->can('delete-category')) {

            $category = Category::find($id);
            $category->delete();
            // return redirect()->route('category.index')->with('success', __('Category Deleted Successfully'));
            return response()->json(['Status'=>'Success','message'=>'Category Deleted Successfully','redirect'=>'category.index'],200);
        } else {
            // return redirect()->back()->with('failed', __('Permission Denied.'));
            return response()->json($data,$data['code']);

        }
    }
}
