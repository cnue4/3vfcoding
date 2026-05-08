<?php
/**
 * FunAdmin
 * ============================================================================
 * 版权所有 2017-2028 FunAdmin，并保留所有权利。
 * 网站地址: http://www.FunAdmin.com
 * ----------------------------------------------------------------------------
 * 采用最新Thinkphp8实现
 * ============================================================================
 * Author: yuege
 * Date: 2021/6/2
 * Time: 16:01
 */

// 加载教务管理路由
use think\facade\Route;
Route::group('edu', function () {
    // 学生管理
    Route::group('student', function () {
        Route::get('/', 'edu.Student/index');
        Route::get('index', 'edu.Student/index');
        Route::get('add', 'edu.Student/add');
        Route::post('add', 'edu.Student/add');
        Route::get('edit', 'edu.Student/edit');
        Route::post('edit', 'edu.Student/edit');
        Route::post('delete', 'edu.Student/delete');
        Route::get('view', 'edu.Student/view');
    });

    // 学生异动
    Route::group('student_change', function () {
        Route::get('/', 'edu.StudentChange/index');
        Route::get('index', 'edu.StudentChange/index');
        Route::get('add', 'edu.StudentChange/add');
        Route::post('add', 'edu.StudentChange/add');
        Route::get('edit', 'edu.StudentChange/edit');
        Route::post('edit', 'edu.StudentChange/edit');
        Route::post('delete', 'edu.StudentChange/delete');
        Route::get('view', 'edu.StudentChange/view');
    });

    // 学生银行
    Route::group('student_bank', function () {
        Route::get('/', 'edu.StudentBank/index');
        Route::get('index', 'edu.StudentBank/index');
        Route::get('add', 'edu.StudentBank/add');
        Route::post('add', 'edu.StudentBank/add');
        Route::get('edit', 'edu.StudentBank/edit');
        Route::post('edit', 'edu.StudentBank/edit');
        Route::post('delete', 'edu.StudentBank/delete');
        Route::get('view', 'edu.StudentBank/view');
    });

    // 续费报名
    Route::group('edu_enrollment', function () {
        Route::get('/', 'edu.EduEnrollment/index');
        Route::get('index', 'edu.EduEnrollment/index');
        Route::get('add', 'edu.EduEnrollment/add');
        Route::post('add', 'edu.EduEnrollment/add');
        Route::get('edit', 'edu.EduEnrollment/edit');
        Route::post('edit', 'edu.EduEnrollment/edit');
        Route::post('delete', 'edu.EduEnrollment/delete');
        Route::get('view', 'edu.EduEnrollment/view');
        Route::get('searchStudent', 'edu.EduEnrollment/searchStudent');
    });

    // 学生银行排行榜
    Route::group('student_bank_ranking', function () {
        Route::get('/', 'edu.StudentBankRanking/index');
        Route::get('index', 'edu.StudentBankRanking/index');
    });

    // 试听体验课
    Route::group('edu_trial_class', function () {
        Route::get('/', 'edu.EduTrialClass/index');
        Route::get('index', 'edu.EduTrialClass/index');
        Route::get('add', 'edu.EduTrialClass/add');
        Route::post('add', 'edu.EduTrialClass/add');
        Route::get('edit', 'edu.EduTrialClass/edit');
        Route::post('edit', 'edu.EduTrialClass/edit');
        Route::post('delete', 'edu.EduTrialClass/delete');
        Route::get('view', 'edu.EduTrialClass/view');
    });

    // 消课明细
    Route::group('edu_lesson_detail', function () {
        Route::get('/', 'edu.EduLessonDetail/index');
        Route::get('index', 'edu.EduLessonDetail/index');
        Route::get('view', 'edu.EduLessonDetail/view');
    });

    // 班级管理
    Route::group('edu_class', function () {
        Route::get('/', 'edu.EduClass/index');
        Route::get('index', 'edu.EduClass/index');
        Route::get('add', 'edu.EduClass/add');
        Route::post('add', 'edu.EduClass/add');
        Route::get('edit', 'edu.EduClass/edit');
        Route::post('edit', 'edu.EduClass/edit');
        Route::post('delete', 'edu.EduClass/delete');
        Route::get('view', 'edu.EduClass/view');
    });

    // 课程管理
    Route::group('edu_course', function () {
        Route::get('/', 'edu.EduCourse/index');
        Route::get('index', 'edu.EduCourse/index');
        Route::get('add', 'edu.EduCourse/add');
        Route::post('add', 'edu.EduCourse/add');
        Route::get('edit', 'edu.EduCourse/edit');
        Route::post('edit', 'edu.EduCourse/edit');
        Route::post('delete', 'edu.EduCourse/delete');
    });

    // 费用预警
    Route::group('fee_warning', function () {
        Route::get('/', 'edu.FeeWarning/index');
        Route::get('index', 'edu.FeeWarning/index');
        Route::get('add', 'edu.FeeWarning/add');
        Route::post('add', 'edu.FeeWarning/add');
        Route::get('edit', 'edu.FeeWarning/edit');
        Route::post('edit', 'edu.FeeWarning/edit');
        Route::post('delete', 'edu.FeeWarning/delete');
        Route::get('view', 'edu.FeeWarning/view');
    });

    // 调课补课停课
    Route::group('schedule_adjust', function () {
        Route::get('/', 'edu.ScheduleAdjust/index');
        Route::get('index', 'edu.ScheduleAdjust/index');
        Route::get('add', 'edu.ScheduleAdjust/add');
        Route::post('add', 'edu.ScheduleAdjust/add');
        Route::get('edit', 'edu.ScheduleAdjust/edit');
        Route::post('edit', 'edu.ScheduleAdjust/edit');
        Route::post('delete', 'edu.ScheduleAdjust/delete');
        Route::get('view', 'edu.ScheduleAdjust/view');
    });

    // 课时结算
    Route::group('teacher_settlement', function () {
        Route::get('/', 'edu.TeacherSettlement/index');
        Route::get('index', 'edu.TeacherSettlement/index');
        Route::get('add', 'edu.TeacherSettlement/add');
        Route::post('add', 'edu.TeacherSettlement/add');
        Route::get('edit', 'edu.TeacherSettlement/edit');
        Route::post('edit', 'edu.TeacherSettlement/edit');
        Route::post('delete', 'edu.TeacherSettlement/delete');
        Route::get('view', 'edu.TeacherSettlement/view');
    });

    // 课时管理
    Route::group('class_hour', function () {
        Route::get('/', 'edu.ClassHour/index');
        Route::get('index', 'edu.ClassHour/index');
        Route::get('add', 'edu.ClassHour/add');
        Route::post('add', 'edu.ClassHour/add');
        Route::get('edit', 'edu.ClassHour/edit');
        Route::post('edit', 'edu.ClassHour/edit');
        Route::post('delete', 'edu.ClassHour/delete');
        Route::get('sign', 'edu.ClassHour/sign');
        Route::post('sign', 'edu.ClassHour/sign');
    });

    // 教师课表
    Route::group('timetable', function () {
        Route::get('/', 'edu.Timetable/index');
        Route::get('index', 'edu.Timetable/index');
        Route::get('export', 'edu.Timetable/export');
        Route::get('print', 'edu.Timetable/printView');
    });

    // 教师课时统计
    Route::group('teacher_hour', function () {
        Route::get('/', 'edu.TeacherHour/index');
        Route::get('index', 'edu.TeacherHour/index');
        Route::get('detail', 'edu.TeacherHour/detail');
        Route::get('export_detail', 'edu.TeacherHour/exportDetail');
        Route::post('save_price', 'edu.TeacherHour/savePrice');
        Route::post('generate', 'edu.TeacherHour/generate');
        Route::post('settle', 'edu.TeacherHour/settle');
        Route::get('export', 'edu.TeacherHour/export');
    });

    // 数据排行
    Route::group('teacher_ranking', function () {
        Route::get('/', 'edu.TeacherRanking/index');
        Route::get('index', 'edu.TeacherRanking/index');
    });

    // 教案资料库
    Route::group('teaching_plan', function () {
        Route::get('/', 'edu.TeachingPlan/index');
        Route::get('index', 'edu.TeachingPlan/index');
        Route::get('add', 'edu.TeachingPlan/add');
        Route::post('add', 'edu.TeachingPlan/add');
        Route::get('edit', 'edu.TeachingPlan/edit');
        Route::post('edit', 'edu.TeachingPlan/edit');
        Route::post('delete', 'edu.TeachingPlan/delete');
        Route::get('download', 'edu.TeachingPlan/download');
    });

    // 教具申请
    Route::group('teaching_aid', function () {
        Route::get('/', 'edu.TeachingAid/index');
        Route::get('index', 'edu.TeachingAid/index');
        Route::get('add', 'edu.TeachingAid/add');
        Route::post('add', 'edu.TeachingAid/add');
        Route::get('edit', 'edu.TeachingAid/edit');
        Route::post('edit', 'edu.TeachingAid/edit');
        Route::post('delete', 'edu.TeachingAid/delete');
        Route::get('approve', 'edu.TeachingAid/approve');
        Route::post('approve', 'edu.TeachingAid/approve');
        Route::post('updateStatus', 'edu.TeachingAid/updateStatus');
    });

    // 校区联系薄
    Route::group('campus_contact', function () {
        Route::get('/', 'edu.CampusContact/index');
        Route::get('index', 'edu.CampusContact/index');
        Route::get('add', 'edu.CampusContact/add');
        Route::post('add', 'edu.CampusContact/add');
        Route::get('edit', 'edu.CampusContact/edit');
        Route::post('edit', 'edu.CampusContact/edit');
        Route::get('view', 'edu.CampusContact/view');
        Route::post('delete', 'edu.CampusContact/delete');
    });
});

