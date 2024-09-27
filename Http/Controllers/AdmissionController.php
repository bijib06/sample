<?php

namespace App\Http\Controllers;

use App\Admission;
use App\Semester;
use Illuminate\Http\Request;

class AdmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('view_admissions')) {
            return redirect()->back();
        }


        $admissions = \App\Admission::orderBy('id', 'DESC')->get();
        return view('admissions.index', compact('admissions'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('create_admissions')) {
            return redirect()->back();
        }


        $semesters = ['' => '-- Select semester from the list --'] + \App\Semester::get()->pluck('name', 'id')->all();
        return view('admissions.create', compact('semesters'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('create_admissions')) {
            return redirect()->back();
        }


        $semester_id = $request->get('semester_id');
        $adm = \App\Admission::whereSemesterId($semester_id)->get();
        if (is_null($adm) || $adm->isEmpty()) {
            \App\Admission::create($request->all());

            $semester = Semester::find($semester_id)->getSemesterName();
            $message = " [ CREATED ADMISSION : ] For Semester ( " . $semester . " ) , Academic year :  ( " . $request['academic_year'] . " ) ,Target : ( " . $request['target'] .
                " ) , Status : ( " . $request['status'] . ") , Closing Date : (" . $request['closing_date'] . " )";
            activity('Admission')->log($message);

            return redirect('/admissions')->withSuccess('Admission created successsfully!');
        }
        return back()->withInput()->withError('Admission not created! Name already exists');
    }

    /**
     * Display the specified resource.
     *
     * @param \App\Admission $admission
     * @return \Illuminate\Http\Response
     */
    public function show(Admission $admission)
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('view_detail_admissions')) {
            return redirect()->back();
        }


        // $admission = \App\Admission::with(['semester','applications'])->find($admission->id);
        // $applications = [];

        // $pending = \App\Application::where('status','Pending')->where('admission_id',$admission->id)->paginate(25);
        // $accepted = \App\Application::where('status','Accepted')->where('admission_id',$admission->id)->orderBy('updated_at','DESC')->paginate(25);
        // $denied = \App\Application::where('status','Denied')->where('admission_id',$admission->id)->orderBy('updated_at','DESC')->paginate(25);
        // $shortlisted = \App\Application::where('status','Shortlisted')->where('admission_id',$admission->id)->paginate(25);
        // $all_applications = \App\Application::paginate(25);
        // $id = $admission->id;

        $c_pending = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Pending')->whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $g_c_pending = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Pending')->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $c_shortlisted = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Shortlisted')->whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $g_c_shortlisted = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Shortlisted')->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $c_accepted = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Accepted')->whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $g_c_accepted = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Accepted')->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();


        $c_denied = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Denied')->whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $g_c_denied = \App\Application::with('program')->selectRaw('program_id,count(program_id) as applicants')
            ->where('status', 'Denied')->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')
            ->where('admission_id', $admission->id)->groupBy('program_id')->get();

        $pending = \App\Application::whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('status', 'Pending')->where('completed','YES')->where('admission_id', $admission->id)->paginate(25);
        $accepted = \App\Application::whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('status', 'Accepted')->where('completed','YES')->where('admission_id', $admission->id)->orderBy('updated_at', 'DESC')->paginate(25);
        $denied = \App\Application::whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('status', 'Denied')->where('completed','YES')->where('admission_id', $admission->id)->orderBy('updated_at', 'DESC')->paginate(25);
        $shortlisted = \App\Application::whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('status', 'Shortlisted')->where('completed','YES')->where('admission_id', $admission->id)->paginate(25);
        $all_applications = \App\Application::whereIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->paginate(25);

        $g_pending = \App\Application::where('status', 'Pending')->where('admission_id', $admission->id)->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->paginate(25);
        $g_accepted = \App\Application::where('status', 'Accepted')->where('admission_id', $admission->id)->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->orderBy('updated_at', 'DESC')->paginate(25);
        $g_denied = \App\Application::where('status', 'Denied')->where('admission_id', $admission->id)->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->orderBy('updated_at', 'DESC')->paginate(25);
        $g_shortlisted = \App\Application::where('status', 'Shortlisted')->where('admission_id', $admission->id)->whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->paginate(25);
        $g_all_applications = \App\Application::whereNotIn('application_level',['UNDERGRADUATE','DIPLOMA'])->where('completed','YES')->paginate(25);

        $id = $admission->id;
        return view('admissions.show2', compact('admission', 'all_applications',
            'pending', 'accepted', 'denied', 'shortlisted', 'id', 'c_pending', 'c_shortlisted', 'c_accepted', 'c_denied',
            'g_c_pending', 'g_c_shortlisted', 'g_c_accepted', 'g_c_denied','g_all_applications', 'g_pending', 'g_accepted',
            'g_denied', 'g_shortlisted'));


    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param \App\Admission $admission
     * @return \Illuminate\Http\Response
     */
    public function edit(Admission $admission)
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('edit_admissions')) {
            return redirect()->back();
        }


        $admission = \App\Admission::find($admission->id);
        $semesters = ['' => '-- Select semester from the list --'] + \App\Semester::get()->pluck('name', 'id')->all();
        return view('admissions.edit', compact('admission', 'semesters'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param \Illuminate\Http\Request $request
     * @param \App\Admission $admission
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Admission $admission)
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }


        if (!auth()->user()->hasPermissionTo('edit_admissions')) {
            return redirect()->back();
        }


        $semester_id = $request->get('semester_id');
        $adm = \App\Admission::whereSemesterId($semester_id)->whereNotIn('id', [$admission->id])->get();
        if (is_null($adm) || $adm->isEmpty()) {
            $old_value = clone $admission;

            $admission->fill($request->all())->save();

            $semester = Semester::find($semester_id)->getSemesterName();
            $message = "[ UPDATED ADMISSION FROM: ]" . " For Semester ".$semester." , Academic year : ".$old_value->academic_year." ,Target : ".$old_value->target .
                " , Status : ".$old_value->status. " , Closing Date : ".$old_value->closing_date." " .
                " [ TO ] : For Semester ".$semester." , Academic year : ".$admission->academic_year." ,Target : ".$admission->target." , Status : ".$admission->status. " 
                , Closing Date : ".$admission->closing_date ." ";
            activity('Admission')->log($message);

            return redirect('/admissions')->withSuccess('Admission updated successsfully!');
        }
        return back()->withInput()->withError('Admission not updated! Name already exists');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param \App\Admission $admission
     * @return \Illuminate\Http\Response
     */
    public function destroy(Admission $admission)
    {

        if(! auth()->user()->isStaff()){
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('delete_admissions')) {
            return redirect()->back();
        }
        try {
            $admission->delete();
            $semester = Semester::find($admission->semester_id)->getSemesterName();
            $message = " [ DELETED ADMISSION  ] : For Semester ".$semester." , Academic year : ".$admission->academic_year." ,Target : ".$admission->target." , Status : ".$admission->status. " 
                , Closing Date : ".$admission->closing_date ." ";
            activity('Admission')->log($message);

            return 'admission deleted successfully';

        }catch (\Exception $exp){
            return 'Error in Deleting Admission '.$exp->getMessage();
        }


    }


    public function exportAdmissionData($id)
    {

        if (!auth()->user()->isStaff()) {
            return redirect()->back();
        }

        if (!auth()->user()->hasPermissionTo('view_admissions')) {
            return redirect()->back();
        }

        $admission = \App\Admission::find($id);
        $data = [];
        $applicants = \App\Application::where('status', 'Accepted')->where('admission_id', $admission->id)->orderBy('updated_at', 'DESC')->get();
        $count = 1;
        foreach ($applicants as $app) {
            try {
                $student = $app->applicant->profile->account->getStudentProfile();
                if (!is_null($student)){
                    $row['MAT#'] = $count;
                    $row['FULL NAME'] = "STUDENT ".$count;
                    $row['AGE'] = $student->getAge();
                    $row['ETHNIC GROUP'] = "";
                    $row['SEX'] = $student->getGender();
                    $row['REGION'] = $student->getRegion();
                    $row['PARENTAL INFO'] = "PARENT ".$count; //$app->guardian_name;
                    $row['SOURCE FUNDING'] = $app->source_of_funding;
                    $row['HIGH SCHOOL'] = $app->educations()->orderBy('created_at', 'DESC')->first()->institution_name;
                    $row['YEAR OF GRADUATION'] = $app->educations()->orderBy('created_at', 'DESC')->first()->to;;

                    $subects_results = $app->examinations->first()->examination_subjects->map(function ($subject){
                        return [
                            "Subject Name" => $subject->subject_name,
                            "Subject Result" => $subject->subject_name,
                        ];
                    });

                    $row["EXAMINATION SUBJECTS"] = $subects_results;

                    $row['Mature/Regular Student'] = $app->applying_as;
                    $row['Year of Admission'] = $admission->academic_year;
                    $row['Major'] = $app->program->name;
                    $gpas = $student->getEnrollSemesters()->map(function ($semester) use ($student) {
                        return [
                            "Semester Name" => $semester->name,
                            "GPA" => $student->getSemesterGPA($semester->id)
                        ];
                    });

                    $row['SEMESTER GPAS'] = $gpas;

//                    foreach ($gpas as $gpa) {
//                        $row[$gpa['name']] = $gpa['gpa'];
//                    }

                    $data[] = $row;

                    $count += 1;

                }


            } catch (\Exception $exp) {
                continue;
            }
        }

        \Excel::create($admission->academic_year . '-' . $admission->semester->name . '-Accepted-Students',
            function ($excel) use ($data) {
                $excel->sheet('ACCEPTED STUDENTS', function ($sheet) use ($data) {
                    $sheet->fromArray($data);
                });

            })->download('xls');
    }
}
