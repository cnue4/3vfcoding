<?php
/**
 * 教务管理路由配置
 */
use think\facade\Route;

// 学生管理
Route::get('backend/edu/student', 'backend.edu.Student/index');
Route::get('backend/edu/student/index', 'backend.edu.Student/index');
Route::get('backend/edu/student/add', 'backend.edu.Student/add');
Route::post('backend/edu/student/add', 'backend.edu.Student/add');
Route::get('backend/edu/student/edit', 'backend.edu.Student/edit');
Route::post('backend/edu/student/edit', 'backend.edu.Student/edit');
Route::post('backend/edu/student/delete', 'backend.edu.Student/delete');
Route::get('backend/edu/student/view', 'backend.edu.Student/view');

// 学生异动
Route::get('backend/edu/student_change', 'backend.edu.StudentChange/index');
Route::get('backend/edu/student_change/index', 'backend.edu.StudentChange/index');
Route::get('backend/edu/student_change/add', 'backend.edu.StudentChange/add');
Route::post('backend/edu/student_change/add', 'backend.edu.StudentChange/add');
Route::get('backend/edu/student_change/edit', 'backend.edu.StudentChange/edit');
Route::post('backend/edu/student_change/edit', 'backend.edu.StudentChange/edit');
Route::post('backend/edu/student_change/delete', 'backend.edu.StudentChange/delete');
Route::get('backend/edu/student_change/view', 'backend.edu.StudentChange/view');

// 学生银行
Route::get('backend/edu/student_bank', 'backend.edu.StudentBank/index');
Route::get('backend/edu/student_bank/index', 'backend.edu.StudentBank/index');
Route::get('backend/edu/student_bank/add', 'backend.edu.StudentBank/add');
Route::post('backend/edu/student_bank/add', 'backend.edu.StudentBank/add');
Route::get('backend/edu/student_bank/edit', 'backend.edu.StudentBank/edit');
Route::post('backend/edu/student_bank/edit', 'backend.edu.StudentBank/edit');
Route::post('backend/edu/student_bank/delete', 'backend.edu.StudentBank/delete');
Route::get('backend/edu/student_bank/view', 'backend.edu.StudentBank/view');

// 续费报名
Route::get('backend/edu/edu_enrollment', 'backend.edu.EduEnrollment/index');
Route::get('backend/edu/edu_enrollment/index', 'backend.edu.EduEnrollment/index');
Route::get('backend/edu/edu_enrollment/add', 'backend.edu.EduEnrollment/add');
Route::post('backend/edu/edu_enrollment/add', 'backend.edu.EduEnrollment/add');
Route::get('backend/edu/edu_enrollment/edit', 'backend.edu.EduEnrollment/edit');
Route::post('backend/edu/edu_enrollment/edit', 'backend.edu.EduEnrollment/edit');
Route::post('backend/edu/edu_enrollment/delete', 'backend.edu.EduEnrollment/delete');
Route::get('backend/edu/edu_enrollment/view', 'backend.edu.EduEnrollment/view');
Route::get('backend/edu/edu_enrollment/searchStudent', 'backend.edu.EduEnrollment/searchStudent');

// 学生银行排行榜
Route::get('backend/edu/student_bank_ranking', 'backend.edu.StudentBankRanking/index');
Route::get('backend/edu/student_bank_ranking/index', 'backend.edu.StudentBankRanking/index');

// 试听体验课
Route::get('backend/edu/edu_trial_class', 'backend.edu.EduTrialClass/index');
Route::get('backend/edu/edu_trial_class/index', 'backend.edu.EduTrialClass/index');
Route::get('backend/edu/edu_trial_class/add', 'backend.edu.EduTrialClass/add');
Route::post('backend/edu/edu_trial_class/add', 'backend.edu.EduTrialClass/add');
Route::get('backend/edu/edu_trial_class/edit', 'backend.edu.EduTrialClass/edit');
Route::post('backend/edu/edu_trial_class/edit', 'backend.edu.EduTrialClass/edit');
Route::post('backend/edu/edu_trial_class/delete', 'backend.edu.EduTrialClass/delete');
Route::get('backend/edu/edu_trial_class/view', 'backend.edu.EduTrialClass/view');

// 消课明细
Route::get('backend/edu/edu_lesson_detail', 'backend.edu.EduLessonDetail/index');
Route::get('backend/edu/edu_lesson_detail/index', 'backend.edu.EduLessonDetail/index');
Route::get('backend/edu/edu_lesson_detail/view', 'backend.edu.EduLessonDetail/view');

// 班级管理
Route::get('backend/edu/class', 'backend.edu.EduClass/index');
Route::get('backend/edu/class/index', 'backend.edu.EduClass/index');
Route::get('backend/edu/class/add', 'backend.edu.EduClass/add');
Route::post('backend/edu/class/add', 'backend.edu.EduClass/add');
Route::get('backend/edu/class/edit', 'backend.edu.EduClass/edit');
Route::post('backend/edu/class/edit', 'backend.edu.EduClass/edit');
Route::post('backend/edu/class/delete', 'backend.edu.EduClass/delete');
Route::get('backend/edu/class/view', 'backend.edu.EduClass/view');

Route::get('backend/edu/edu_class', 'backend.edu.EduClass/index');
Route::get('backend/edu/edu_class/index', 'backend.edu.EduClass/index');
Route::get('backend/edu/edu_class/add', 'backend.edu.EduClass/add');
Route::post('backend/edu/edu_class/add', 'backend.edu.EduClass/add');
Route::get('backend/edu/edu_class/edit', 'backend.edu.EduClass/edit');
Route::post('backend/edu/edu_class/edit', 'backend.edu.EduClass/edit');
Route::post('backend/edu/edu_class/delete', 'backend.edu.EduClass/delete');
Route::get('backend/edu/edu_class/view', 'backend.edu.EduClass/view');

// 课程管理
Route::get('backend/edu/course', 'backend.edu.EduCourse/index');
Route::get('backend/edu/course/index', 'backend.edu.EduCourse/index');
Route::get('backend/edu/course/add', 'backend.edu.EduCourse/add');
Route::post('backend/edu/course/add', 'backend.edu.EduCourse/add');
Route::get('backend/edu/course/edit', 'backend.edu.EduCourse/edit');
Route::post('backend/edu/course/edit', 'backend.edu.EduCourse/edit');
Route::post('backend/edu/course/delete', 'backend.edu.EduCourse/delete');
Route::get('backend/edu/course/categoryList', 'backend.edu.EduCourse/categoryList');

Route::get('backend/edu/edu_course', 'backend.edu.EduCourse/index');
Route::get('backend/edu/edu_course/index', 'backend.edu.EduCourse/index');
Route::get('backend/edu/edu_course/add', 'backend.edu.EduCourse/add');
Route::post('backend/edu/edu_course/add', 'backend.edu.EduCourse/add');
Route::get('backend/edu/edu_course/edit', 'backend.edu.EduCourse/edit');
Route::post('backend/edu/edu_course/edit', 'backend.edu.EduCourse/edit');
Route::post('backend/edu/edu_course/delete', 'backend.edu.EduCourse/delete');
Route::get('backend/edu/edu_course/categoryList', 'backend.edu.EduCourse/categoryList');

// 课时管理
Route::get('backend/edu/schedule_adjust', 'backend.edu.ScheduleAdjust/index');
Route::get('backend/edu/schedule_adjust/index', 'backend.edu.ScheduleAdjust/index');
Route::get('backend/edu/schedule_adjust/add', 'backend.edu.ScheduleAdjust/add');
Route::post('backend/edu/schedule_adjust/add', 'backend.edu.ScheduleAdjust/add');
Route::get('backend/edu/schedule_adjust/edit', 'backend.edu.ScheduleAdjust/edit');
Route::post('backend/edu/schedule_adjust/edit', 'backend.edu.ScheduleAdjust/edit');
Route::get('backend/edu/schedule_adjust/view', 'backend.edu.ScheduleAdjust/view');
Route::post('backend/edu/schedule_adjust/delete', 'backend.edu.ScheduleAdjust/delete');

// 费用预警
Route::get('backend/edu/fee_warning', 'backend.edu.FeeWarning/index');
Route::get('backend/edu/fee_warning/index', 'backend.edu.FeeWarning/index');
Route::get('backend/edu/fee_warning/add', 'backend.edu.FeeWarning/add');
Route::post('backend/edu/fee_warning/add', 'backend.edu.FeeWarning/add');
Route::get('backend/edu/fee_warning/edit', 'backend.edu.FeeWarning/edit');
Route::post('backend/edu/fee_warning/edit', 'backend.edu.FeeWarning/edit');
Route::get('backend/edu/fee_warning/view', 'backend.edu.FeeWarning/view');
Route::post('backend/edu/fee_warning/delete', 'backend.edu.FeeWarning/delete');

// 课时结算
Route::get('backend/edu/teacher_settlement', 'backend.edu.TeacherSettlement/index');
Route::get('backend/edu/teacher_settlement/index', 'backend.edu.TeacherSettlement/index');
Route::get('backend/edu/teacher_settlement/add', 'backend.edu.TeacherSettlement/add');
Route::post('backend/edu/teacher_settlement/add', 'backend.edu.TeacherSettlement/add');
Route::get('backend/edu/teacher_settlement/edit', 'backend.edu.TeacherSettlement/edit');
Route::post('backend/edu/teacher_settlement/edit', 'backend.edu.TeacherSettlement/edit');
Route::get('backend/edu/teacher_settlement/view', 'backend.edu.TeacherSettlement/view');
Route::post('backend/edu/teacher_settlement/delete', 'backend.edu.TeacherSettlement/delete');

// 课时管理
Route::get('backend/edu/class_hour', 'backend.edu.ClassHour/index');
Route::get('backend/edu/class_hour/index', 'backend.edu.ClassHour/index');
Route::get('backend/edu/class_hour/add', 'backend.edu.ClassHour/add');
Route::post('backend/edu/class_hour/add', 'backend.edu.ClassHour/add');
Route::get('backend/edu/class_hour/edit', 'backend.edu.ClassHour/edit');
Route::post('backend/edu/class_hour/edit', 'backend.edu.ClassHour/edit');
Route::get('backend/edu/class_hour/view', 'backend.edu.ClassHour/view');
Route::post('backend/edu/class_hour/delete', 'backend.edu.ClassHour/delete');
Route::get('backend/edu/class_hour/sign', 'backend.edu.ClassHour/sign');
Route::post('backend/edu/class_hour/sign', 'backend.edu.ClassHour/sign');

// 教师课表
Route::get('backend/edu/timetable', 'backend.edu.Timetable/index');
Route::get('backend/edu/timetable/index', 'backend.edu.Timetable/index');
Route::get('backend/edu/timetable/export', 'backend.edu.Timetable/export');
Route::get('backend/edu/timetable/print', 'backend.edu.Timetable/printView');

// 教师课时统计
Route::get('backend/edu/teacher_hour', 'backend.edu.TeacherHour/index');
Route::get('backend/edu/teacher_hour/index', 'backend.edu.TeacherHour/index');
Route::get('backend/edu/teacher_hour/detail', 'backend.edu.TeacherHour/detail');
Route::get('backend/edu/teacher_hour/export_detail', 'backend.edu.TeacherHour/exportDetail');
Route::post('backend/edu/teacher_hour/save_price', 'backend.edu.TeacherHour/savePrice');
Route::post('backend/edu/teacher_hour/generate', 'backend.edu.TeacherHour/generate');
Route::post('backend/edu/teacher_hour/settle', 'backend.edu.TeacherHour/settle');
Route::get('backend/edu/teacher_hour/export', 'backend.edu.TeacherHour/export');

// 数据排行
Route::get('backend/edu/teacher_ranking', 'backend.edu.TeacherRanking/index');
Route::get('backend/edu/teacher_ranking/index', 'backend.edu.TeacherRanking/index');

// 教案资料库
Route::get('backend/edu/teaching_plan', 'backend.edu.TeachingPlan/index');
Route::get('backend/edu/teaching_plan/index', 'backend.edu.TeachingPlan/index');
Route::get('backend/edu/teaching_plan/add', 'backend.edu.TeachingPlan/add');
Route::post('backend/edu/teaching_plan/add', 'backend.edu.TeachingPlan/add');
Route::get('backend/edu/teaching_plan/edit', 'backend.edu.TeachingPlan/edit');
Route::post('backend/edu/teaching_plan/edit', 'backend.edu.TeachingPlan/edit');
Route::post('backend/edu/teaching_plan/delete', 'backend.edu.TeachingPlan/delete');
Route::get('backend/edu/teaching_plan/download', 'backend.edu.TeachingPlan/download');

// 教具申请
Route::get('backend/edu/teaching_aid', 'backend.edu.TeachingAid/index');
Route::get('backend/edu/teaching_aid/index', 'backend.edu.TeachingAid/index');
Route::get('backend/edu/teaching_aid/add', 'backend.edu.TeachingAid/add');
Route::post('backend/edu/teaching_aid/add', 'backend.edu.TeachingAid/add');
Route::get('backend/edu/teaching_aid/edit', 'backend.edu.TeachingAid/edit');
Route::post('backend/edu/teaching_aid/edit', 'backend.edu.TeachingAid/edit');
Route::post('backend/edu/teaching_aid/delete', 'backend.edu.TeachingAid/delete');
Route::get('backend/edu/teaching_aid/approve', 'backend.edu.TeachingAid/approve');
Route::post('backend/edu/teaching_aid/approve', 'backend.edu.TeachingAid/approve');
Route::post('backend/edu/teaching_aid/updateStatus', 'backend.edu.TeachingAid/updateStatus');

// 校区联系薄
Route::get('backend/edu/campus_contact', 'backend.edu.CampusContact/index');
Route::get('backend/edu/campus_contact/index', 'backend.edu.CampusContact/index');
Route::get('backend/edu/campus_contact/add', 'backend.edu.CampusContact/add');
Route::post('backend/edu/campus_contact/add', 'backend.edu.CampusContact/add');
Route::get('backend/edu/campus_contact/edit', 'backend.edu.CampusContact/edit');
Route::post('backend/edu/campus_contact/edit', 'backend.edu.CampusContact/edit');
Route::get('backend/edu/campus_contact/view', 'backend.edu.CampusContact/view');
Route::post('backend/edu/campus_contact/delete', 'backend.edu.CampusContact/delete');
