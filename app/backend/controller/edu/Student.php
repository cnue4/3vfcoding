<?php
/**
 * 学生管理控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\Student as StudentModel;
use app\backend\model\EduClass;
use app\backend\model\EduCourse;
use app\backend\model\EduClassHour;
use app\common\controller\Backend;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use think\App;
use think\exception\HttpResponseException;
use think\facade\Db;
use think\facade\Validate;
use think\facade\View;
use app\common\annotation\ControllerAnnotation;
use app\common\annotation\NodeAnnotation;

/**
 * @ControllerAnnotation(title="学生管理")
 * Class Student
 * @package app\backend\controller\edu
 */
class Student extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new StudentModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            try {
                StudentModel::syncLeaveStatusBatch();
                if ($this->request->param('selectFields')) {
                    $this->selectList();
                }

                list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
                $sort = ['id' => 'asc'];
                $keyword = trim((string) $this->request->param('keyword', ''));

                $countQuery = $this->modelClass
                    ->with(['eduClass', 'eduCourse'])
                    ->where($where);
                $listQuery = $this->modelClass
                    ->with(['eduClass', 'eduCourse'])
                    ->where($where);
                $this->applyTeacherClassScope($countQuery, 'class_id');
                $this->applyTeacherClassScope($listQuery, 'class_id');

                if ($keyword !== '') {
                    $countQuery->where('name|student_no|contact_name|phone', 'like', '%' . $keyword . '%');
                    $listQuery->where('name|student_no|contact_name|phone', 'like', '%' . $keyword . '%');
                }

                $count = $countQuery->count();

                $list = $listQuery
                    ->order($sort)
                    ->page($this->page, $this->pageSize)
                    ->select()
                    ->toArray();

                foreach ($list as &$item) {
                    $item = $this->normalizeStudentRow($item);
                }
                unset($item);

                return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
            } catch (\Throwable $e) {
                return json(['code' => 1, 'msg' => $e->getMessage(), 'data' => [], 'count' => 0]);
            }
        }

        return view();
    }

    /**
     * @NodeAnnotation(title="添加")
     */
    public function add()
    {
        if ((int) $this->request->param('download_template', 0) === 1) {
            return $this->downloadImportTemplate();
        }

        if ($this->request->isPost() && ((int) $this->request->param('import_mode', 0) === 1 || $this->request->file('file'))) {
            return $this->handleImportRequest();
        }

        if ((int) $this->request->param('import_mode', 0) === 1) {
            View::assign([
                'templateHeaders' => ['姓名', '性别', '邮箱', '联系人', '联系电话', '班级', '课程', '学生状态', '地址', '备注'],
            ]);
            return view('import');
        }

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $data = $this->buildStudentData($post);
            $this->assertTeacherOwnsClass((int) ($data['class_id'] ?? 0));
            $data['student_no'] = StudentModel::generateStudentNo();

            $result = $this->modelClass->save($data);
            if ($result) {
                $this->updateClassStudentCount($data['class_id']);
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([]));
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->with(['eduClass', 'eduCourse'])->find($id);
        if (!$model) {
            $this->error('学生不存在');
        }
        $this->assertTeacherOwnsClass((int) ($model->class_id ?? 0));

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $oldClassId = (int) $model->class_id;
            $oldData = $model->toArray();
            $data = $this->buildStudentData($post, $oldData);
            $this->assertTeacherOwnsClass((int) ($data['class_id'] ?? 0));

            $result = $model->save($data);
            if ($result) {
                $this->updateClassStudentCount($oldClassId);
                if ($oldClassId !== (int) $data['class_id']) {
                    $this->updateClassStudentCount((int) $data['class_id']);
                }
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        $formData = $this->normalizeStudentRow($model->toArray());
        View::assign($this->buildFormViewData($formData));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ? $this->request->param('ids') : $this->request->param('id');
        if (empty($ids)) {
            $this->error(lang('Ids can not empty'));
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        $classIds = [];
        foreach ($list as $item) {
            $this->assertTeacherOwnsClass((int) ($item->class_id ?? 0));
            $classIds[] = (int) $item->class_id;
        }

        try {
            foreach ($list as $student) {
                $student->delete();
            }
            foreach (array_unique($classIds) as $classId) {
                $this->updateClassStudentCount($classId);
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        StudentModel::syncLeaveStatusBatch();
        $studentModel = $this->modelClass->with(['eduClass', 'eduCourse'])->find($id);
        if (!$studentModel) {
            $this->error('学生不存在');
        }
        $this->assertTeacherOwnsClass((int) ($studentModel->class_id ?? 0));
        $formData = $this->normalizeStudentRow($studentModel->toArray());

        View::assign([
            'formData' => $formData,
            'genderList' => StudentModel::GENDER_LIST,
            'studentStatusList' => StudentModel::STUDENT_STATUS_LIST,
            'statusList' => StudentModel::STATUS_LIST,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="批量导入")
     */
    public function batch_import()
    {
        return $this->add();
    }

    public function batchImport()
    {
        return $this->add();
    }

    /**
     * @NodeAnnotation(title="下载导入模板")
     */
    public function batch_import_template()
    {
        return $this->downloadImportTemplate();
    }

    public function batchImportTemplate()
    {
        return $this->downloadImportTemplate();
    }

    protected function handleImportRequest()
    {
        $file = $this->request->file('file');
        if (!$file) {
            return json(['code' => 0, 'msg' => '请先上传Excel文件']);
        }

        $extension = strtolower((string) $file->extension());
        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return json(['code' => 0, 'msg' => '仅支持上传 xlsx / xls 文件']);
        }

        $savedPath = $file->move(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'student_import');
        if (!$savedPath) {
            return json(['code' => 0, 'msg' => '文件保存失败']);
        }

        $fullPath = $savedPath->getRealPath();
        if (!$fullPath) {
            return json(['code' => 0, 'msg' => '导入文件不存在']);
        }

        try {
            $spreadsheet = IOFactory::load($fullPath);
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            return json(['code' => 0, 'msg' => 'Excel解析失败：' . $e->getMessage()]);
        }

        if (count($rows) <= 1) {
            return json(['code' => 0, 'msg' => '导入文件没有学生数据']);
        }

        $successCount = 0;
        $errors = [];
        $importKeys = [];
        Db::startTrans();
        try {
            foreach ($rows as $index => $row) {
                if ($index === 0) {
                    continue;
                }

                $name = $this->normalizeImportCell($row[0] ?? '');
                $genderText = $this->normalizeImportCell($row[1] ?? '');
                $email = $this->normalizeImportCell($row[2] ?? '');
                $contactName = $this->normalizeImportCell($row[3] ?? '');
                $phone = $this->normalizeImportCell($row[4] ?? '');
                $className = $this->normalizeImportCell($row[5] ?? '');
                $courseName = $this->normalizeImportCell($row[6] ?? '');
                $studentStatusText = $this->normalizeImportCell($row[7] ?? '在读');
                $address = $this->normalizeImportCell($row[8] ?? '');
                $remark = $this->normalizeImportCell($row[9] ?? '');

                if ($name === '' && $contactName === '' && $phone === '' && $className === '' && $courseName === '') {
                    continue;
                }

                try {
                    if ($name === '') {
                        throw new \Exception('姓名不能为空');
                    }
                    if ($contactName === '') {
                        throw new \Exception('联系人不能为空');
                    }
                    if ($phone === '') {
                        throw new \Exception('联系电话不能为空');
                    }
                    if ($className === '') {
                        throw new \Exception('班级不能为空');
                    }
                    if ($courseName === '') {
                        throw new \Exception('课程不能为空');
                    }

                    $duplicateKey = $this->buildImportDuplicateKey($name, $phone);
                    if (isset($importKeys[$duplicateKey])) {
                        throw new \Exception('姓名 + 联系电话重复，已跳过');
                    }
                    $exists = StudentModel::where('name', $name)
                        ->where('phone', $phone)
                        ->whereNull('delete_time')
                        ->find();
                    if ($exists) {
                        throw new \Exception('姓名 + 联系电话已存在，已跳过');
                    }

                    $gender = $this->parseGenderValue($genderText);
                    $studentStatus = $this->parseStudentStatusValue($studentStatusText);
                    $classQuery = EduClass::where('name', $className);
                    if ($this->isTeacherRole()) {
                        $classQuery->where('teacher_id', $this->getCurrentAdminId());
                    }
                    $classId = (int) $classQuery->value('id');
                    if (!$classId) {
                        throw new \Exception('班级不存在：' . $className);
                    }

                    $courseId = (int) EduCourse::where('name', $courseName)->value('id');
                    if (!$courseId) {
                        $courseId = (int) EduClass::where('id', $classId)->value('course_id');
                    }
                    if (!$courseId) {
                        throw new \Exception('课程不存在：' . $courseName);
                    }

                    $data = $this->buildImportStudentData([
                        'name' => $name,
                        'gender' => $gender,
                        'email' => $email,
                        'address' => $address,
                        'contact_name' => $contactName,
                        'phone' => $phone,
                        'class_id' => $classId,
                        'course_id' => $courseId,
                        'student_status' => $studentStatus,
                        'remark' => $remark,
                        'status' => 1,
                    ]);
                    $data['student_no'] = StudentModel::generateStudentNo();
                    $studentModel = new StudentModel();
                    $studentModel->save($data);
                    $importKeys[$duplicateKey] = true;
                    $successCount++;
                } catch (HttpResponseException $e) {
                    $message = $this->extractImportErrorMessage($e->getResponse()->getData());
                    $errors[] = '第' . ($index + 1) . '行：' . ($message ?: '数据校验失败');
                } catch (\Throwable $e) {
                    $errors[] = '第' . ($index + 1) . '行：' . ($e->getMessage() ?: '数据校验失败');
                }
            }

            if ($successCount > 0) {
                $classIds = $this->modelClass->distinct(true)->column('class_id');
                foreach ($classIds as $classId) {
                    $this->updateClassStudentCount((int) $classId);
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }

        if ($successCount === 0) {
            return json(['code' => 0, 'msg' => $errors ? implode('；', array_slice($errors, 0, 3)) : '没有成功导入任何学生']);
        }

        $message = '成功导入 ' . $successCount . ' 条学生数据';
        if (!empty($errors)) {
            $message .= '，失败 ' . count($errors) . ' 条：' . implode('；', array_slice($errors, 0, 2));
        }
        return json(['code' => 1, 'msg' => $message]);
    }

    protected function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['姓名', '性别', '邮箱', '联系人', '联系电话', '班级', '课程', '学生状态', '地址', '备注'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->buildExcelCell($index + 1, 1), $header);
        }

        $examples = [
            ['张三', '男', 'demo1@example.com', '张家长', '13800000001', '一年级启蒙班', 'Scratch启蒙', '在读', '武汉市洪山区', '示例数据'],
            ['李四', '女', 'demo2@example.com', '李家长', '13800000002', '二年级进阶班', 'Python入门', '在读', '武汉市武昌区', '示例数据'],
        ];
        foreach ($examples as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue($this->buildExcelCell($columnIndex + 1, $rowIndex + 2), $value);
            }
        }

        $directory = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'student_import_template';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $fileName = '学生批量导入模板.xlsx';
        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);

        return download($filePath, $fileName, false, 180);
    }

    protected function buildExcelCell(int $columnNumber, int $rowNumber): string
    {
        $column = '';
        while ($columnNumber > 0) {
            $mod = ($columnNumber - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $columnNumber = (int) floor(($columnNumber - 1) / 26);
        }
        return $column . $rowNumber;
    }

    protected function normalizeImportCell($value): string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return '';
        }
        $text = preg_replace('/^[\x{FEFF}\x{200B}\x{00A0}\s]+|[\x{FEFF}\x{200B}\x{00A0}\s]+$/u', '', $text);
        return trim((string) $text);
    }

    protected function buildImportDuplicateKey(string $name, string $phone): string
    {
        return md5($name . '|' . preg_replace('/\s+/', '', $phone));
    }

    protected function parseGenderValue(string $value): int
    {
        $normalized = trim($value);
        if ($normalized === '' || in_array($normalized, ['男', '1'], true)) {
            return 1;
        }
        if (in_array($normalized, ['女', '0'], true)) {
            return 0;
        }
        throw new \Exception('性别仅支持填写“男”或“女”');
    }

    protected function parseStudentStatusValue(string $value): int
    {
        $normalized = trim($value);
        if ($normalized === '' || $normalized === '在读' || $normalized === '1') {
            return 1;
        }
        if ($normalized === '休学' || $normalized === '2') {
            return 2;
        }
        if ($normalized === '退学' || $normalized === '3') {
            return 3;
        }
        if ($normalized === '请假' || $normalized === '4') {
            return 4;
        }
        throw new \Exception('学生状态仅支持填写“在读 / 休学 / 退学 / 请假”');
    }

    protected function buildImportStudentData(array $post): array
    {
        return $this->buildStudentPayload($post, []);
    }

    protected function extractImportErrorMessage($responseData): string
    {
        if (is_array($responseData)) {
            $msg = trim((string) ($responseData['msg'] ?? ''));
            if ($msg !== '') {
                return $msg;
            }
        }
        return '';
    }

    protected function buildStudentData(array $post, array $currentData = []): array
    {
        return $this->buildStudentPayload($post, $currentData);
    }

    protected function buildStudentPayload(array $post, array $currentData = []): array
    {
        $classId = (int) ($post['class_id'] ?? 0);
        $courseId = (int) ($post['course_id'] ?? 0);
        if ($classId && !$courseId) {
            $courseId = (int) EduClass::where('id', $classId)->value('course_id');
            $post['course_id'] = $courseId;
        }

        $validator = Validate::rule([
            'name|姓名' => 'require|max:50',
            'gender|性别' => 'require|in:0,1',
            'email|邮箱' => 'max:100',
            'address|地址' => 'max:255',
            'contact_name|联系人' => 'require|max:50',
            'phone|联系电话' => 'require|max:20',
            'class_id|班级' => 'require',
            'course_id|课程' => 'require',
            'student_status|学生状态' => 'require|in:1,2,3,4',
            'remark|学生备注' => 'max:500',
            'status|系统状态' => 'require|in:1,2',
        ]);
        if (!$validator->check($post)) {
            throw new \Exception($validator->getError() ?: '数据校验失败');
        }

        $hours = StudentModel::buildHourTemplateByClass($classId, $currentData);

        return [
            'name' => trim((string) ($post['name'] ?? '')),
            'gender' => (int) ($post['gender'] ?? 1),
            'email' => trim((string) ($post['email'] ?? '')),
            'address' => trim((string) ($post['address'] ?? '')),
            'contact_name' => trim((string) ($post['contact_name'] ?? '')),
            'phone' => trim((string) ($post['phone'] ?? '')),
            'class_id' => $classId,
            'course_id' => $courseId,
            'total_hours' => $hours['total_hours'],
            'remaining_hours' => $hours['remaining_hours'],
            'student_status' => (int) ($post['student_status'] ?? 1),
            'remark' => trim((string) ($post['remark'] ?? '')),
            'status' => (int) ($post['status'] ?? 1),
        ];
    }

    protected function normalizeStudentRow(array $item): array
    {
        $classId = (int) ($item['class_id'] ?? 0);
        $courseId = (int) ($item['course_id'] ?? 0);
        $classData = $item['eduClass'] ?? ($item['edu_class'] ?? []);
        $courseData = $item['eduCourse'] ?? ($item['edu_course'] ?? []);

        if ($classId > 0 && empty($classData)) {
            $classData = EduClass::where('id', $classId)->find();
            $classData = $classData ? $classData->toArray() : [];
        }
        if ($courseId > 0 && empty($courseData)) {
            $courseData = EduCourse::where('id', $courseId)->find();
            $courseData = $courseData ? $courseData->toArray() : [];
        }

        $hasBoundClass = $classId > 0;

        $item['class_name'] = $classData['name'] ?? ($item['class_name'] ?? ($hasBoundClass ? '' : '未绑定班级'));
        $item['course_name'] = $courseData['name'] ?? ($classData['course_name'] ?? ($item['course_name'] ?? ($hasBoundClass ? '' : '未绑定课程')));
        $item['gender_text'] = StudentModel::GENDER_LIST[$item['gender'] ?? null] ?? '';
        $item['student_status_text'] = StudentModel::STUDENT_STATUS_LIST[$item['student_status'] ?? null] ?? '';
        $item['status_text'] = StudentModel::STATUS_LIST[$item['status'] ?? null] ?? '';
        $item['contact_name'] = $item['contact_name'] ?? '';
        $item['address'] = $item['address'] ?? '';
        $item['remark'] = $item['remark'] ?? '';
        $item['enrollment_gift_hours'] = round(StudentModel::getEnrollmentGiftHoursByStudent((int) ($item['id'] ?? 0)), 2);
        $item['change_gift_hours'] = round(StudentModel::getChangeGiftHoursByStudent((int) ($item['id'] ?? 0)), 2);
        $item['delete_gift_hours'] = round(StudentModel::getDeleteGiftHoursByStudent((int) ($item['id'] ?? 0)), 2);
        $item['gift_hours'] = round(max($item['enrollment_gift_hours'] + $item['change_gift_hours'] - $item['delete_gift_hours'], 0), 2);

        if (!$hasBoundClass) {
            $item['class_name'] = '未绑定班级';
            $item['course_name'] = $item['course_name'] ?: '未绑定课程';
            $item['total_hours'] = '未绑定班级';
            $item['remaining_hours'] = '未绑定班级';
            $item['used_hours'] = '未绑定班级';
            return $item;
        }

        if (empty($classData)) {
            $item['class_name'] = $item['class_name'] ?: ('班级ID：' . $classId);
            $item['course_name'] = $item['course_name'] ?: ($courseId > 0 ? ('课程ID：' . $courseId) : '未绑定课程');
        }

        $studentTotalHours = round((float) ($item['total_hours'] ?? ($classData['total_hours'] ?? 0)), 2);
        $studentRemainingHours = round((float) ($item['remaining_hours'] ?? ($classData['remaining_hours'] ?? 0)), 2);
        $item['total_hours'] = $studentTotalHours;
        $item['remaining_hours'] = $studentRemainingHours;
        $item['used_hours'] = round(max($studentTotalHours - $studentRemainingHours, 0), 2);

        return $item;
    }

    protected function buildFormViewData(array $formData): array
    {
        $classQuery = EduClass::where('status', 1)->order('id asc');
        if ($this->isTeacherRole()) {
            $classQuery->where('teacher_id', $this->getCurrentAdminId());
        }

        return [
            'formData' => $formData,
            'classList' => $classQuery->column('name', 'id'),
            'courseList' => EduCourse::where('status', 1)->order('id asc')->column('name', 'id'),
            'genderList' => StudentModel::GENDER_LIST,
            'studentStatusList' => StudentModel::STUDENT_STATUS_LIST,
            'hasActiveLeave' => isset($formData['id']) ? ((int) ($formData['student_status'] ?? 0) === 4) : false,
            'statusList' => StudentModel::STATUS_LIST,
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }

    protected function updateClassStudentCount($classId)
    {
        $classId = (int) $classId;
        if (!$classId) {
            return;
        }

        $count = $this->modelClass
            ->where('class_id', $classId)
            ->where('student_status', 1)
            ->where('status', 1)
            ->count();

        EduClass::where('id', $classId)->update(['current_students' => $count]);
    }
}
