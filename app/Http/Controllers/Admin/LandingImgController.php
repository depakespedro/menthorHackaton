<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\LandingImg;
use App\Http\Requests\LandingImgForm;

class LandingImgController extends Controller
{
    
    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(LandingImg $landingimg)
    {
        
        return view('admin.landingimg.edit')
            ->with('landingimg', $landingimg);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(LandingImgForm $request, LandingImg $landingimg)
    {
        
        $landingimg->update([
            'title' => $request->title,
            'url' => $request->url,
            'text' => $request->text,
        ]);     
        
        if ($image = $request->img){
            $img_pref = 'section2';
            if ($landingimg->section_id == 5) {$img_pref = 'partner';}
            $landingimg->addImage($image, $img_pref);
        }    
        
        return redirect()->route('landings.index');
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function delete($landingimg)
    {
        try { 
            
            $landingimg_row = LandingImg::find($landingimg);
            
            $landingimg_row->deleteImage();
            $landingimg_row->delete();
            
            return response()->json([
                'success' => true,
            ], 200);    
            
        } catch (Exception $e) {
            echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
        }
    }
    
    /**
     * Remove image
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteImage($landingimg)
    {
        $landingimg_row = LandingImg::find($landingimg);
            
        $landingimg_row->deleteImage();

        return response()->json([
            'success' => true,
        ], 200);
    }
}
