<?php
/**
 * 校区联系薄控制器
 */
namespace app\backend\controller\edu;

use app\backend\model\CampusContact as CampusContactModel;
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
 * @ControllerAnnotation(title="校区联系薄")
 * Class CampusContact
 * @package app\backend\controller\edu
 */
class CampusContact extends Backend
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        $this->modelClass = new CampusContactModel();
    }

    /**
     * @NodeAnnotation(title="列表")
     */
    public function index()
    {
        if ($this->request->isAjax()) {
            if ($this->request->param('selectFields')) {
                $this->selectList();
            }

            list($this->page, $this->pageSize, $sort, $where) = $this->buildParames();
            $sort = ['id' => 'asc'];
            $keyword = trim((string) $this->request->param('keyword', ''));
            $position = (int) $this->request->param('position', 1);
            $allowedPositions = array_keys(CampusContactModel::POSITION_LIST);

            $query = $this->modelClass->where($where)->whereIn('position', $allowedPositions);
            if ($keyword === '' && $position > 0 && in_array($position, $allowedPositions, true)) {
                $query->where('position', $position);
            }
            if ($keyword !== '') {
                $query->where('name|phone|email', 'like', '%' . $keyword . '%');
            }

            $count = $query->count();
            $list = $query->order($sort)->page($this->page, $this->pageSize)->select()->toArray();

            foreach ($list as &$item) {
                $item['gender_text'] = CampusContactModel::GENDER_LIST[$item['gender'] ?? null] ?? '';
                $item['position_text'] = CampusContactModel::POSITION_LIST[$item['position'] ?? null] ?? '';
            }
            unset($item);

            return json(['code' => 0, 'msg' => lang('get formData success'), 'data' => $list, 'count' => $count]);
        }

        View::assign([
            'positionList' => CampusContactModel::POSITION_LIST,
        ]);
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
                'templateHeaders' => ['姓名', '性别', '标签', '电话', '邮箱', '备注'],
            ]);
            return view('import');
        }

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $data = $this->buildContactData($post);

            $result = $this->modelClass->save($data);
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData([
            'position' => (int) $this->request->param('position', 1),
        ]));
        return view();
    }

    /**
     * @NodeAnnotation(title="编辑")
     */
    public function edit()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error(lang('record not found'));
        }

        if ($this->request->isPost()) {
            $post = $this->request->post();
            $data = $this->buildContactData($post);

            $result = $model->save($data);
            if ($result) {
                $this->success(lang('operation success'));
            }
            $this->error(lang('operation failed'));
        }

        View::assign($this->buildFormViewData($model->toArray()));
        return view('add');
    }

    /**
     * @NodeAnnotation(title="查看")
     */
    public function view()
    {
        $id = (int) $this->request->param('id');
        $model = $this->modelClass->find($id);
        if (!$model) {
            $this->error(lang('record not found'));
        }

        $formData = $model->toArray();
        $formData['gender_text'] = CampusContactModel::GENDER_LIST[$formData['gender'] ?? null] ?? '';
        $formData['position_text'] = CampusContactModel::POSITION_LIST[$formData['position'] ?? null] ?? '';

        View::assign([
            'formData' => $formData,
        ]);
        return view();
    }

    /**
     * @NodeAnnotation(title="删除")
     */
    public function delete()
    {
        $ids = $this->request->param('ids') ?: $this->request->param('id');
        if (empty($ids)) {
            $this->error(lang('Ids can not empty'));
        }

        $list = $this->modelClass->where('id', 'in', $ids)->select();
        if ($list->isEmpty()) {
            $this->error(lang('record not found'));
        }

        try {
            foreach ($list as $item) {
                $item->delete();
            }
        } catch (\Exception $e) {
            $this->error(lang($e->getMessage()));
        }

        $this->success(lang('operation success'));
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

        $savedPath = $file->move(root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'campus_contact_import');
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
            return json(['code' => 0, 'msg' => '导入文件没有联系人数据']);
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
                $positionText = $this->normalizeImportCell($row[2] ?? '');
                $phone = $this->normalizeImportCell($row[3] ?? '');
                $email = $this->normalizeImportCell($row[4] ?? '');
                $remark = $this->normalizeImportCell($row[5] ?? '');

                if ($name === '' && $phone === '' && $email === '' && $remark === '') {
                    continue;
                }

                try {
                    if ($name === '') {
                        throw new \Exception('姓名不能为空');
                    }
                    if ($phone === '') {
                        throw new \Exception('电话不能为空');
                    }

                    $duplicateKey = $this->buildImportDuplicateKey($name, $phone);
                    if (isset($importKeys[$duplicateKey])) {
                        throw new \Exception('姓名 + 电话重复，已跳过');
                    }

                    $exists = $this->modelClass->where('name', $name)
                        ->where('phone', $phone)
                        ->whereNull('delete_time')
                        ->find();
                    if ($exists) {
                        throw new \Exception('姓名 + 电话已存在，已跳过');
                    }

                    $data = $this->buildImportContactData([
                        'name' => $name,
                        'gender' => $this->parseGenderValue($genderText),
                        'position' => $this->parsePositionValue($positionText),
                        'phone' => $phone,
                        'email' => $email,
                        'remark' => $remark,
                    ]);

                    CampusContactModel::create($data);
                    $importKeys[$duplicateKey] = true;
                    $successCount++;
                } catch (HttpResponseException $e) {
                    $message = $this->extractImportErrorMessage($e->getResponse()->getData());
                    $errors[] = '第' . ($index + 1) . '行：' . ($message ?: '数据校验失败');
                } catch (\Throwable $e) {
                    $errors[] = '第' . ($index + 1) . '行：' . ($e->getMessage() ?: '数据校验失败');
                }
            }

            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return json(['code' => 0, 'msg' => $e->getMessage()]);
        }

        if ($successCount === 0) {
            return json(['code' => 0, 'msg' => $errors ? implode('；', array_slice($errors, 0, 3)) : '没有成功导入任何联系人']);
        }

        $message = '成功导入 ' . $successCount . ' 条联系人数据';
        if (!empty($errors)) {
            $message .= '，失败 ' . count($errors) . ' 条：' . implode('；', array_slice($errors, 0, 2));
        }
        return json(['code' => 1, 'msg' => $message]);
    }

    protected function downloadImportTemplate()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $headers = ['姓名', '性别', '标签', '电话', '邮箱', '备注'];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue($this->buildExcelCell($index + 1, 1), $header);
        }

        $examples = [
            ['易元宗', '男', '校区领导', '17623386831', 'yiguangzong@msn.com', 'XX小学班主任'],
            ['张老师', '女', '本部老师', '13800000001', 'teacher1@example.com', 'Scratch主讲老师'],
            ['李老师', '男', '兼职老师', '13800000002', 'teacher2@example.com', '周末兼职授课'],
        ];
        foreach ($examples as $rowIndex => $rowValues) {
            foreach ($rowValues as $columnIndex => $value) {
                $sheet->setCellValue($this->buildExcelCell($columnIndex + 1, $rowIndex + 2), $value);
            }
        }

        foreach (range(1, count($headers)) as $columnNumber) {
            $sheet->getColumnDimension($this->buildExcelCell($columnNumber, 1, false))->setWidth(20);
        }

        $fileName = '校区联系模板.xlsx';
        return $this->downloadExcel($spreadsheet, $fileName);
    }

    protected function downloadExcel(Spreadsheet $spreadsheet, string $fileName)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $directory = root_path() . 'runtime' . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'campus_contact_import_template';
        if (!is_dir($directory)) {
            @mkdir($directory, 0755, true);
        }

        $filePath = $directory . DIRECTORY_SEPARATOR . $fileName;
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($filePath);

        return download($filePath)->name($fileName)->expire(0);
    }

    protected function buildExcelCell(int $columnNumber, int $rowNumber, bool $includeRowNumber = true): string
    {
        $column = '';
        while ($columnNumber > 0) {
            $mod = ($columnNumber - 1) % 26;
            $column = chr(65 + $mod) . $column;
            $columnNumber = (int) floor(($columnNumber - 1) / 26);
        }

        return $includeRowNumber ? $column . $rowNumber : $column;
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

    protected function parseGenderValue(string $genderText): int
    {
        $normalized = trim($this->normalizeImportCell($genderText));
        if ($normalized === '' || in_array($normalized, ['男', '1'], true)) {
            return 1;
        }
        if (in_array($normalized, ['女', '0'], true)) {
            return 0;
        }
        throw new \Exception('性别仅支持填写“男”或“女”');
    }

    protected function parsePositionValue(string $positionText): int
    {
        $normalized = trim($this->normalizeImportCell($positionText));
        if ($normalized === '' || in_array($normalized, ['校区领导', '1'], true)) {
            return 1;
        }
        if (in_array($normalized, ['本部老师', '2'], true)) {
            return 2;
        }
        if (in_array($normalized, ['兼职老师', '3'], true)) {
            return 3;
        }

        throw new \Exception('标签仅支持填写“校区领导 / 本部老师 / 兼职老师”');
    }

    protected function buildImportDuplicateKey(string $name, string $phone): string
    {
        return md5($name . '|' . preg_replace('/\s+/', '', $phone));
    }

    protected function buildImportContactData(array $post): array
    {
        $validator = Validate::rule([
            'name|姓名' => 'require|max:50',
            'gender|性别' => 'require|in:0,1',
            'position|标签' => 'require|in:1,2,3',
            'phone|电话' => 'require|regex:/^\d{11}$/',
            'email|邮箱' => 'max:100',
            'remark|备注' => 'max:500',
        ]);
        if (!$validator->check($post)) {
            throw new \Exception($validator->getError() ?: '数据校验失败');
        }

        return [
            'name' => trim((string) ($post['name'] ?? '')),
            'gender' => (int) ($post['gender'] ?? 1),
            'position' => (int) ($post['position'] ?? 1),
            'phone' => trim((string) ($post['phone'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'remark' => trim((string) ($post['remark'] ?? '')),
        ];
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

    protected function buildContactData(array $post): array
    {
        $rule = [
            'name|姓名' => 'require|max:50',
            'gender|性别' => 'require|in:0,1',
            'position|职务' => 'require|in:1,2,3',
            'phone|电话' => 'require|regex:/^\d{11}$/',
            'email|邮箱' => 'email|max:100',
            'remark|备注' => 'max:500',
        ];
        $this->validate($post, $rule);

        return [
            'name' => trim((string) ($post['name'] ?? '')),
            'gender' => (int) ($post['gender'] ?? 1),
            'position' => (int) ($post['position'] ?? 1),
            'phone' => trim((string) ($post['phone'] ?? '')),
            'email' => trim((string) ($post['email'] ?? '')),
            'remark' => trim((string) ($post['remark'] ?? '')),
        ];
    }

    protected function buildFormViewData(array $formData): array
    {
        return [
            'formData' => $formData,
            'genderList' => CampusContactModel::GENDER_LIST,
            'positionList' => CampusContactModel::POSITION_LIST,
            'title' => isset($formData['id']) ? lang('Edit') : lang('Add'),
        ];
    }
}
