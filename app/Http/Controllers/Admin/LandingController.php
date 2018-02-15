<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\LandingForm;
use App\Models\Landings;
use App\Models\LandingImg;
use App\Models\Survey;

class LandingController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $landings = Landings::orderBy('order', 'desc')->orderBy('created_at', 'desc')->get();

        return view('admin.landings.index')
            ->with('landings', $landings);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $landing = new Landings;
        $surveys = Survey::orderBy('created_at', 'desc')->get(); 
        
        $partnersImgs = [];
        $section2Imgs = [];
        
        return view('admin.landings.create')
            ->with('allLandings', Landings::all())
            ->with('landing', $landing)
            ->with('partnersImgs', $partnersImgs)   
            ->with('section2Imgs', $section2Imgs) 
            ->with('allSurveys', $surveys);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(LandingForm $request, Landings $landing)
    {
        
        $newlanding = $landing->create([
            'order' => $request->order,
            'name' => $request->name,
            'published' => $request->published? $request->published : 0,
            'url' => $request->url,
            'section_3_survey_link' => $request->section_3_survey_link,
            'seo_title' => $request->seo_title,
            'seo_keywords' => $request->seo_keywords,
            'seo_description' => $request->seo_description,
            'section_1' => $request->section_1,
            'section_1_title' => $request->section_1_title,
            'section_2' => $request->section_2,
            'section_2_title' => $request->section_2_title,
            'section_3' => $request->section_3,
            'section_4' => $request->section_4,
            'section_4_text2' => $request->section_4_text2,
            'section_4_text3' => $request->section_4_text3,
        ]);
        
        if ($image = $request->seo_img)
            $newlanding->addImage($image, 'seo_img');
        
        if ($image = $request->section_1_img)
            $newlanding->addImage($image, 'section_1_img');
        
        if ($image = $request->section_3_img)
            $newlanding->addImage($image, 'section_3_img');
        
        if ($image = $request->section_4_img)
            $newlanding->addImage($image, 'section_4_img');
        
        if ($image = $request->section_4_img2)
            $newlanding->addImage($image, 'section_4_img2');
        
        if ($image = $request->section_4_img3)
            $newlanding->addImage($image, 'section_4_img3');
        
        if ($image = $request->section2_image){
            
            $newlandingImg = \App\Models\LandingImg::create([
                'landings_id' => $newlanding->id,
                'section_id' => '2',                
                'title' => $request->section2_image_title ? $request->section2_image_title : null,
                'url' => $request->section2_image_url ? $request->section2_image_url : null,
                'text' => $request->section2_image_text ? $request->section2_image_text : null,
            ]);
            $newlandingImg->addImage($image,'section2');
        }  
        
        if ($image = $request->partner_image){
            
            $newlandingImg = \App\Models\LandingImg::create([
                'landings_id' => $newlanding->id,
                'section_id' => '5',                
                'title' => $request->partner_image_title ? $request->partner_image_title : null,
                'url' => $request->partner_image_url ? $request->partner_image_url : null,
                'text' => $request->partner_image_text ? $request->partner_image_text : null,
            ]);
            $newlandingImg->addImage($image,'partner');
        }  
        
        return redirect()->route('landings.index');
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show(Landings $landing)
    {
        //$this->authorize('resource', $landing);

        return view('admin.landings.show')
            ->with('landing', $landing);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit(Landings $landing)
    {
        //$this->authorize('resource', $landing);
        
        $surveys = Survey::orderBy('created_at', 'desc')->get(); 
        $partnersImgs = LandingImg::wherelandings_id($landing->id)->wheresection_id(5)->get();
        $section2Imgs = LandingImg::wherelandings_id($landing->id)->wheresection_id(2)->get();
        
        return view('admin.landings.edit')
            ->with('landing', $landing)
            ->with('partnersImgs', $partnersImgs)   
            ->with('section2Imgs', $section2Imgs) 
            ->with('allSurveys', $surveys);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(LandingForm $request, Landings $landing)
    {
        //$this->authorize('resource', $landing);

        $landing->update([
            'order' => $request->order,
            'name' => $request->name,
            'published' => $request->published? $request->published : 0,
            'url' => $request->url,
            'section_3_survey_link' => $request->section_3_survey_link,
            'seo_title' => $request->seo_title,
            'seo_keywords' => $request->seo_keywords,
            'seo_description' => $request->seo_description,
            'section_1' => $request->section_1,
            'section_1_title' => $request->section_1_title,
            'section_2' => $request->section_2,
            'section_2_title' => $request->section_2_title,
            'section_3' => $request->section_3,
            'section_4' => $request->section_4,
            'section_4_text2' => $request->section_4_text2,
            'section_4_text3' => $request->section_4_text3,
        ]);
             
        
        if ($image = $request->seo_img)
            $landing->addImage($image, 'seo_img');
        
        if ($image = $request->section_1_img)
            $landing->addImage($image, 'section_1_img');
        
        if ($image = $request->section_3_img)
            $landing->addImage($image, 'section_3_img');
        
        if ($image = $request->section_4_img)
            $landing->addImage($image, 'section_4_img');
        
        if ($image = $request->section_4_img2)
            $landing->addImage($image, 'section_4_img2');
        
        if ($image = $request->section_4_img3)
            $landing->addImage($image, 'section_4_img3');
        
        if ($image = $request->section2_image){
            
            $newsection2Img = \App\Models\LandingImg::create([
                'landings_id' => $landing->id,
                'section_id' => '2',                
                'title' => $request->section2_image_title ? $request->section2_image_title : null,
                'url' => $request->section2_image_url ? $request->section2_image_url : null,
                'text' => $request->section2_image_text ? $request->section2_image_text : null,
            ]);
            $newsection2Img->addImage($image,'section2');
        }  
        
        if ($image = $request->partner_image){
            
            $newpartnerImg = \App\Models\LandingImg::create([
                'landings_id' => $landing->id,
                'section_id' => '5',
                'title' => $request->partner_image_title ? $request->partner_image_title : null,
                'url' => $request->partner_image_url ? $request->partner_image_url : null,
                'text' => $request->partner_image_text ? $request->partner_image_text : null,
            ]);
            $newpartnerImg->addImage($image,'partner');
        }    

        return redirect()->route('landings.index');
    }
    
    
    /**
     * Remove image
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function deleteImage(Landings $landing, $field_name)
    {
        $landing->deleteImage($field_name);

        return response()->json([
            'success' => true,
        ], 200);
    }
        
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy(Landings $landing)
    {
        //$this->authorize('resource', $landing);
        
        $landing->delete();

        return redirect()->route('landings.index');
    }
}
