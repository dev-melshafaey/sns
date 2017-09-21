<?php

require_once("Rest.inc.php");
require_once('pdo.php');
require_once 'jwt/src/BeforeValidException.php';
require_once 'jwt/src/ExpiredException.php';
require_once 'jwt/src/SignatureInvalidException.php';
require_once 'jwt/src/JWT.php';

use \Firebase\JWT\JWT;

class API extends REST {

    public $data = "";

    public function __construct() {
        parent::__construct();   
    }

    public function processApi() {
        $db = new Database();
        $db->query("SET NAMES 'utf8'");
        $db->execute();
        $db = null;
        $func = strtolower(trim(str_replace("/", "", $_REQUEST['x'])));
        if ((int) method_exists($this, $func) > 0)
            $this->$func();
        else
            $this->response('', 404); 
    }

    private function login() {
        if ($this->get_request_method() != "POST") {
            $this->response('', 406);
        }
        $user = json_decode(file_get_contents("php://input"), true);
        $username = $user['username'];
        $password = $user['password'];

        if (!empty($username) and ! empty($password)) {

            $db = new Database();
            $db->query("SELECT * FROM parents WHERE `username` = :username  AND `password`= :password");
            $db->bind(':username', $username);
            $db->bind(':password', $password);
            $db->execute();
            $result = $db->resultset();
            if (!empty($result)) {
                $key = '1991';
                $token = array(
                    "iss" => "SNS",
                    "iat" => null,
                    "exp" => null,
                    "aud" => "",
                    "sub" => "",
                    "id" => $result[0]['id']
                );
                $jwt = JWT::encode($token, $key);

                $this->response($this->json([true, $jwt]), 200); 
            } else {
                $this->response($this->json([false]), 200);
            }
        }

        $this->response($this->json([false]), 400);
        $db = null;
    }

    private function getChilds() {
        $data = json_decode(file_get_contents("php://input"), true);
        $parent_id = $this->getParentId($data['token']);
        $announce_id = $data['announce_id'];
        $abs_id = $data['abscense_id'];
        $acd_id = $data['academic_id'];
        $ach_id = $data['achivement_id'];
        $video_id = $data['video_id'];
        $image_id = $data['image_id'];
        $db = new Database();

        $query = "select * from students WHERE parents_id = :parent_id";
        $db->query($query);
        $db->bind(':parent_id', $parent_id);
        $db->execute();
        $result = $db->resultset();
        $obj = new stdClass();
        $arr = [];
        foreach ($result as $key => $value) {
            $itemList = [$value['id'], $value['class_id'], $value['grade_id'], $value['school_id']];
            $annocNO = $this->getAnnouncments($announce_id, $itemList, 1);
            $absNO = $this->getAbscense($abs_id, $itemList, 1);
            $acdNO = $this->getAcademics($acd_id, $itemList, 1);
            $achNO = $this->getAchievments($ach_id, $itemList, 1);
            $imgNO = $this->getImageGallery($image_id, $itemList, 1);
            $vidNO = $this->getVideoGallery($video_id, $itemList, 1);
            $obj->$value['id'] = [$annocNO, $absNO, $acdNO, $achNO, $imgNO, $vidNO];
        }
        array_push($arr, $obj);
        $db = null;
        $this->response($this->json([$result, $arr]), 200);
    }

    private function getNotificationDetails() {
        $data = json_decode(file_get_contents("php://input"), true);
        $this->getParentId($data['token']);
        $student_id = $data['student_id'];
        $type_id = $data['id'];
        $cat = $data['cat'];
        $db = new Database();
        $query = "SELECT * FROM `students` WHERE id = :id";
        $db->query($query);
        $db->bind(':id', $student_id);
        $db->execute();
        $result = $db->resultset();
        $student = [$student_id, $result[0]['class_id'], $result[0]['grade_id'], $result[0]['school_id']];

        if ($cat == 'announcment') {
            $result = $this->getAnnouncments($type_id, $student, 2);
        } else if ($cat == 'abscense') {
            $result = $this->getAbscense($type_id, $student, 2);
        } else if ($cat == 'academics') {
            $result = $this->getAcademics($type_id, $student, 2);
        } else if ($cat == 'achievments') {
            $result = $this->getAchievments($type_id, $student, 2);
        } else if ($cat == 'image') {
            $result = $this->getImageGallery($type_id, $student, 2);
        } else if ($cat == 'video') {
            $result = $this->getVideoGallery($type_id, $student, 2);
        } else {
            $result = [false];
        }

        $db = null;
        $this->response($this->json($result), 200);
    }

    private function getVideoGallery($video_id, $student, $num) {
        $db = new Database();
        $query = "SELECT * FROM `videogallery` WHERE id > :video_id and school_id=:school_id and (grades_id=:grade_id or grades_id is null) and (classes_id= :class_id or classes_id is null)";
        $db->query($query);
        $db->bind(':video_id', $video_id);
        $db->bind(':class_id', $student[1]);
        $db->bind(':grade_id', $student[2]);
        $db->bind(':school_id', $student[3]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function getImageGallery($image_id, $student, $num) {
        $db = new Database();
        $query = "SELECT * FROM `imagegallery` WHERE id > :image_id and school_id=:school_id and (grades_id=:grade_id or grades_id is null) and (classes_id= :class_id or classes_id is null)";
        $db->query($query);
        $db->bind(':image_id', $image_id);
        $db->bind(':class_id', $student[1]);
        $db->bind(':grade_id', $student[2]);
        $db->bind(':school_id', $student[3]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function getAchievments($ach_id, $student, $num) {
        $db = new Database();
        $query = "select * from achivementslist where id > :ach_id and students_id = :student_id";
        $db->query($query);
        $db->bind(':ach_id', $ach_id);
        $db->bind(':student_id', $student[0]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function getAbscense($abs_id, $student, $num) {
        $db = new Database();
        $query = "select * from absencelist where id > :abs_id and students_id = :student_id";
        $db->query($query);
        $db->bind(':abs_id', $abs_id);
        $db->bind(':student_id', $student[0]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function getAnnouncments($ann_id, $student, $num) {
        $db = new Database();
        $query = "SELECT * FROM `announcment` WHERE id > :ann_id and school_id=:school_id and (grades_id=:grade_id or grades_id is null) and (classes_id= :class_id or classes_id is null)";
        $db->query($query);
        $db->bind(':ann_id', $ann_id);
        $db->bind(':class_id', $student[1]);
        $db->bind(':grade_id', $student[2]);
        $db->bind(':school_id', $student[3]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function getAcademics($acd_id, $student, $num) {
        $db = new Database();
        $query = "SELECT * FROM academics,subjects WHERE academics.id > :acd_id and academics.school_id=:school_id and (academics.grades_id = :grade_id) and (classes_id= :class_id or classes_id is null) and academics.subjects_id = subjects.id";
        $db->query($query);
        $db->bind(':acd_id', $acd_id);
        $db->bind(':class_id', $student[1]);
        $db->bind(':grade_id', $student[2]);
        $db->bind(':school_id', $student[3]);
        $db->execute();
        $result = $db->resultset();
        $count = $db->rowCount();
        $db = null;
        $re = 0;
        if ($num == 2) {
            $re = $result;
        } else {
            $re = $count;
        }
        return $re;
    }

    private function insertFeedBack() {
        $data = json_decode(file_get_contents("php://input"), true);
        $parent_id = $this->getParentId($data['token']);
        $student_id = $data['student_id'];
        $source = $data['source'];
        $item_id = $data['item_id'];
        $subject = $data['subject'];
        $body = $data['body'];
        $db = new Database();
        $query = "insert into feedback (parent_id,student_id,date,source,item_id,subject,body) values (:parent_id,:student_id,now(),:source,:item_id,:subject,:body)";
        $db->query($query);
        $db->bind(':parent_id', $parent_id);
        $db->bind(':student_id', $student_id);
        $db->bind(':source', $source);
        $db->bind(':item_id', $item_id);
        $db->bind(':subject', $subject);
        $db->bind(':body', $body);
        if ($db->execute()) {
            $this->response($this->json([true]), 400);
        }
        $db = null;
    }

    private function getParentId($jwt) {
        $key = '1991';
        try {
            $decoded = JWT::decode($jwt, $key, array('HS256'));
            $unencodedData = (array) $decoded;
            $parent_id = $unencodedData['id'];
            return $parent_id;
        } catch (Exception $e) {
            $this->response($this->json([false]), 200);
        }
    }

    private function json($data) {
        if (is_array($data)) {
            return json_encode($data);
        }
    }

}

$api = new API;
$api->processApi();
?>