<?php

namespace App\Http\Controllers\API;

use App\Program;
use App\RunningCourse;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class StudentController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function studentDashboard()
    {
        /**
         * Calculate credit hours left and make it a chart
         *  Show total payments or balance
         * Show Student GPA
         * Show total courses, total registered semesters,show year, show status
         * Show Registration Dates
         */
        try {
            $user = auth()->user();

        } catch (\Exception $exp) {
            $message = $exp->getMessage();
            return $this->errorResponse($message);
        }
    }

    public function studentGrades()
    {

        try {
            $user = auth()->user();
            $student = $user->getStudentProfile();

            if (!is_null($student)) {

                $data = [];
                foreach ($student->grades as $grade) {
                    if ($grade->isVisible()) {
                        $row['courseCode'] = $grade->running_course->course->course->code;
                        $row['courseName'] = $grade->running_course->course->course->code;
                        $row['semester'] = $grade->running_course->semester->name;
                        $row['test'] = $grade->test;
                        $row['assignments'] = $grade->assignments;
                        $row['finalExam'] = $grade->final_exam;

                        $data[] = $row;
                    }
                }
            }
            return $this->successResponse($data, "success");

        } catch (\Exception $exp) {
            $message = $exp->getMessage();
            return $this->errorResponse($message);
        }
    }

    public function currentSemesterRunningCourses()
    {
        try {
            $user = auth()->user();
            $student = $user->getStudentProfile();
            $current_semester = \App\Utility::getCurrentSemester($student->type);
            if (!is_null($current_semester)) {
                $data = [];
                $courses_array = [];
                
                if (!is_null($student)) {
                    if (!is_null($student->program)) {
                        $program = $student->program;
                        $program_level_courses = Program::where('level', $program->level)->get();
                        foreach ($program_level_courses as $pls) {
                            $data = $pls->getRuningCourseForSemester($current_semester->id);
                            foreach ($data as $d) {
                                array_push($courses_array, $d);
                            }
                        }
                    }
                }

                $courses = RunningCourse::whereIn('id', $courses_array)
                    ->orderBy('start_date', 'DESC')->get();
                foreach ($courses as $course) {
                    $row['Semester'] = $course->semester->name;
                    $row['Name'] = $course->getName();
                    $row['Code'] = $course->course->course->code;
                    $row['Lecturer'] = $course->lecturer->getName();
                    $row['Venue'] = $course->venue;
                    $row['LectureRoom'] = $course->lecture_room;
                    $row['Time'] = $course->time;
                    $row['isFull'] = $course->isFull() ? 'YES' : 'NO';

                    $data[] = $row;
                }
            }
            return $this->successResponse($data, "success");
        } catch (\Exception $exp) {
            $message = $exp->getMessage();
            return $this->errorResponse($message);
        }
    }
    public function programCourses()
    {
        try {
            $data = [];
            $user = auth()->user();
            $student = $user->getStudentProfile();
            if (!is_null($student)){
                if (!is_null($student->program)){
                    $program = $student->program;
                    $program_courses = $program->program_courses;
                    foreach ($program_courses as $course){
                        $row['Program'] = $program->name;
                        $row['CourseCode'] = $course->course->code;
                        $row['CourseName'] = $course->course->name;
                        $row['CreditHours'] = $course->credit_hours;
                        $row['Type'] = $course->type;

                        $data[] = $row;
                    }
                }
            }

            return $this->successResponse($data, "success");

        }catch (\Exception $exp){
            $message = $exp->getMessage();
            return $this->errorResponse($message);
        }
    }
    public function studentTranscript()
    {
    }

    public function studentPayments()
    {
        try {
            $data = [];
            $user = auth()->user();
            $student = $user->getStudentProfile();
            if (!is_null($student)){
                $payments = $student->payments;
                foreach ($payments as $payment){
                    $row['PaymentType'] = $payment->payment_type->name;
                    $row['Semester'] = $payment->semester->name;
                    $row['Amount'] = $payment->amount;
                    $row['ReceiptNo'] = $payment->receipt_no;
                    $row['Date'] = $payment->date;

                    $data[] = $row;
                }
            }

            return $this->successResponse($data, "success");

        }catch (\Exception $exp){
            $message = $exp->getMessage();
            return $this->errorResponse($message);
        }
    }

    public function studentRefunds()
    {
    }

    public function studentDefferals()
    {
    }

    protected function successResponse($data, $message)
    {
        return response()->json([
            'code' => 1,
            'data' => $data,
            'message' => $message
        ]);
    }

    protected function errorResponse($data, $message)
    {
        return response()->json([
            'code' => 0,
            'error' => $message
        ]);
    }
}
