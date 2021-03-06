<?php
// +----------------------------------------------------------------------
// | Changjunese Scrore v2.0
// +----------------------------------------------------------------------
// | Copyright (c) 2015-2017 https://www.northme.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: Victor.Chen <victor.chen@northme.com>
// +----------------------------------------------------------------------
// | Create at: 2017/10/20 23:11
// +----------------------------------------------------------------------
namespace Score\Controller;
use Curl\Curl;
use Score\Model\OperatorsModel;
use Score\Model\QueryExamsModel;
use Score\Model\RemoteServersModel;
use Think\Exception;

class OperatorController extends BaseController
{
    public function _initialize()
    {
        parent::_initialize(); // TODO: Change the autogenerated stub
        $Operators = new OperatorsModel();
        if (!$Operators->getOperatorIdByUid(getUID()))
        {
            $this->error('你正在访问仅限数据特派员访问的页面，拒绝访问','/',3);
        }
    }

    /**
     * 公共：特派员信息
     */
    public function pub_my(){

    }

    /**
     * 数据特派员：考试管理
     */
    public function dataop_exams(){
        $Exams = new QueryExamsModel();
        $exams_available = $Exams->getAvaliableExamsDetail();
        $this->assign('exams_available',$exams_available);
        $this->assign('SideBar_Selected','dataop_exams');
        $this->meta_title = '特派员::考试管理';
        $this->display();
    }

    /**
     * AJAX:考试信息修改
     */
    public function Action_dataop_exams_edit($exam_id){
        ApiResponseHeader();
        $Exams = new QueryExamsModel();
        switch (I('get.action')) {
            case 'get':
                $exam_info = $Exams->getInfoByExamId($exam_id);
                echo json_encode(array(
                    'success' => true,
                    'exam_name' => $exam_info['exam_name'],
                    'exam_end_at' => $exam_info['exam_end_at'],
                    'mark_status' => $exam_info['mark_status'],
                    'query_status' => $exam_info['query_status']
                ));
                break;
            case 'update':
                $Exams->where(array("id"=>$exam_id))->data(array(I('post.type')=>I('post.value'),"update_at"=>getDateTime()))->save();
                echo json_encode(array("success"=>true,"msg"=>"已将考试(id:".$exam_id.')的'.I('post.type').'状态修改为'.I('post.value')));
                break;
        }
    }

    /**
     * AJAX:考试项目创建
     */
    public function Action_dataop_exams_create() {
        $exam_name = I('post.exam_name');
        $exam_end_at = I('post.exam_end_at');
        $Exams = new QueryExamsModel();
        $Exams->initNewExam($exam_name,$exam_end_at);
        echo json_encode(array(
            "success" => true,
            "msg" => "已经成功地创建了项目，名称：".$exam_name,
        ));
    }

    /**
     * 数据特派员：数据导入
     */
    public function dataop_import(){
        $Exams = new QueryExamsModel();
        $exams_available = $Exams->getAvaliableExamsDetail();

        //获取远程数据
        $RemoteServers = new RemoteServersModel();
        $remote_exams = $RemoteServers->select();
        $this->assign('remote_servers',$remote_exams);
        $this->assign('remote_servers2',$remote_exams);

        $this->assign('exams_available',$exams_available);
        $this->assign('SideBar_Selected','dataop_import');
        $this->meta_title = '特派员：数据导入';
        $this->display();
    }

    /**
     * 从远程服务器获取考试
     */
    public function Action_dataop_import_getRemoteExams(){
        ApiResponseHeader();
        $server_id = I('post.server_id');
        $RemoteServers = new RemoteServersModel();
        $Exams = new QueryExamsModel();
        $imported_count = $Exams->getImportedAmount(I('post.exam_id'));
        echo json_encode(array(
            "success" => true,
            "msg" => "已经成功从服务器上拉取考试信息",
            "remote_exams" => $RemoteServers->SVR_PullAllExams($server_id,$RemoteServers->getServerDetailById($server_id)['type']),
            "imported_school_class" => $imported_count['school_class'],
            "imported_students" => $imported_count['students'],
            "imported_result" => $Exams->getImportedResultAmount(I('post.exam_id')),
        ));
    }

    /**
     * 删除考试（实际为调整状态为DELETED）
     * @param $exam_id
     */
    public function dataop_exams_delete($exam_id)
    {
        $Exams = new QueryExamsModel();
        $Exams->where(array("id"=>$exam_id))->save(array("query_status"=>"DELETED"));
        redirect('/operator/exams');
    }

    public function dataop_exams_importProcess($action)
    {
        ApiResponseHeader();
        $Exam = new QueryExamsModel();
        $exam_id = I('post.exam_id');
        $RemoteServers = new RemoteServersModel();
        switch ($action)
        {
            case 'clearImportedData':
                $Exam->clearImportedData($exam_id);
                echo json_encode(array(
                    "success" => true,
                    "msg" => "已经清空！",
                ));
                break;
            case 'importStudentData':
                $server_id = I('post.server_id');
                $RemoteServers = new RemoteServersModel();
                $remote_exam_SN = I('post.remote_examSN');
                $SQL = 'SELECT [stuSN] as stu_sn, [stuClaSN] as stu_cla_sn, [stuCode] as "考号", [stuName] as "姓名" FROM [SE_DB_PRO_eMark_'.$remote_exam_SN.'].[dbo].[tbl_exa_student]';
                $exec_res = $RemoteServers->SVR_ExecuteSQL($server_id,$SQL);//信息
                //$Exam->where(array("id",$exam_id))->save(array("update_at"=>getDateTime()));

                $school_class_count = $Exam->importStudents($exam_id,$exec_res);
                echo json_encode(array(
                    "success" => true,
                    "msg" => "成功导入".$school_class_count.'名学生信息',
                    "importedStudents" => $school_class_count,
                ));
                break;
            case 'importSchoolClassData':
                $server_id = I('post.server_id');
                $RemoteServers = new RemoteServersModel();
                $remote_exam_SN = I('post.remote_examSN');
                $SQL = 'SELECT [stuClaSN] as stu_cla_sn, [stuClaName] as class_name, [stuClaSchoolName] as school_name FROM [SE_DB_PRO_eMark_'.$remote_exam_SN.'].[dbo].[tbl_exa_student_class]';
                $exec_res = $RemoteServers->SVR_ExecuteSQL($server_id,$SQL);
                $school_class_count = $Exam->importSchoolClass($exam_id,$exec_res);
                $SQL = 'SELECT ALL * FROM [tbl_exa_project] WHERE prjSN = '.$remote_exam_SN;
                $exec_res2 = $RemoteServers->SVR_ExecuteSQL($server_id,$SQL);//考试范围类别
                $Exam->where(array("id",$exam_id))->save(array("type"=>$exec_res2[0]['prjRange'],"update_at"=>getDateTime()));
                echo json_encode(array(
                    "success" => true,
                    "msg" => "成功导入",
                    "importedSchoolClass" => $school_class_count,
                ));
                break;
            case 'mergeData':

                break;
            case 'getAvailableTables':
                $server_id = I('post.server_id');
                $remote_exam_SN = I('post.remote_examSN');
                $server_type = $RemoteServers->getServerDetailById($server_id)['type'];
                switch ($server_type) {
                    case 'APMS':
                        $SQL = 'USE SE_DB_PRO_eMark_'.$remote_exam_SN.';'.'SELECT ALL * FROM [tbl_tas_excel] WHERE [excUnionSN] = 1 AND [excCode] = 105';
                        break;
                    case 'AMEQP':
                        $SQL = 'USE AC_AMEQP_'.$remote_exam_SN.';'.'SELECT ALL * FROM [tbl_tas_excel] WHERE [excUnionSN] = 1 AND [excCode] = 105';
                        break;
                }
                $rs = $RemoteServers->SVR_ExecuteSQL($server_id,$SQL,$server_type);
                foreach ($rs as $key=>$value) {
                    switch ($value['excName']) {
                        case '学生成绩':
                            $rs[$key]['excType2'] = 'A';
                            break;
                        case '学生成绩文科':
                            $rs[$key]['excType2'] = 'B';
                            break;
                        case '学生成绩理科':
                            $rs[$key]['excType2'] = 'C';
                            break;
                    }
                }
                echo json_encode(array(
                    "success" => true,
                    "remote_exams"=>$rs
                ));
                break;
            case 'pullRemoteResult':
                $exam_id = I('post.exam_id');
                $server_id = I('post.server_id');
                $remote_examSN = I('post.remote_examSN');
                $import_table_type = I('post.importTableType');
                $server_type = $RemoteServers->getServerDetailById($server_id)['type'];
                switch ($server_type) {
                    case 'APMS':
                        $SQL = 'SELECT ALL * FROM [SE_DB_PRO_eMark_'.$remote_examSN.'].[dbo].[tbl_xls_score_'.$import_table_type.']';
                        break;
                    case 'AMEQP':
                        $SQL = 'SELECT ALL * FROM [AC_AMEQP_'.$remote_examSN.'].[dbo].[tbl_xls_score_'.$import_table_type.']';
                        break;
                }
                $RemoteServers = new RemoteServersModel();
                $exam_result = $RemoteServers->SVR_ExecuteSQL($server_id,$SQL,$server_type);//成绩
                for ($i=0;$i<count($exam_result);$i++) {
                    $rsc = array();
                    foreach ((array)$exam_result[$i] as $key=>$value) {
                        if (strpos($key,'|') != false) {
                            $subject_name = substr($key,0,strpos($key,'|'));
                            $subject_subname = substr($key,strpos($key,'|')+1,strlen($key)+1);
                            $rsc[$subject_name][$subject_subname] = $value;
                        } else {
                            $rsc = array();
                        }
                    }
                    $new_exam_result[$i] = array(
                        "姓名" => $exam_result[$i]['姓名'],
                        "考号" => $exam_result[$i]['考号'],
                        "stu_sn" => $exam_result[$i]['stuSN'],
                        "result" => serialize($rsc)
                    );
                }
                $Exam = new QueryExamsModel();
                $imported_count = $Exam->importResult($exam_id,$new_exam_result);//这里的数量为 [已导入数量]/[学生数量] 形式

                //更改考试状态
                $Exam->where(array("id"=>$exam_id))->save(array("mark_status"=>"END","query_status"=>"PROCESSING","update_at"=>getDateTime()));
                echo json_encode(array(
                    "success" => true,
                    "msg" => "已经成功导入".$imported_count."名考生的成绩到系统中",
                    "imported_count" => $Exam->getImportedResultAmount($exam_id)
                ));
                break;
            case 'clearImportedResult':
                $exam_id = I('post.exam_id');
                $Exam = new QueryExamsModel();
                $Exam->clearImportedResult($exam_id);
                echo json_encode(array(
                    "success" => true,
                    "msg" => "已经清空成绩，请导入数据",
                    "imported_count" => $Exam->getImportedResultAmount($exam_id),
                ));
                break;
        }
    }

    public function test2(){
        ApiResponseHeader();
        $r = new RemoteServersModel();
        $ees= $r->SVR_PullAllExams(6,'AMEQP');
        var_dump($ees);
    }
}