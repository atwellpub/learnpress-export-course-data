<?php

if ( !class_exists('LearnPress_Export_Course_Data_Settings_Page')	) {

    /**
     * Class LearnPress_Export_Course_Data_Settings_Page
     */
    class LearnPress_Export_Course_Data_Settings_Page {

        static $csv_file_path;
        static $csv_file_urlpath;
        static $csv_file;

        /**
         * Main LearnPress_Export_Course_Data_Settings_Page Instance
         */
        public function __construct() {
            self::load_hooks();
        }

        /**
         * Load hooks and filters
         */
        public static function load_hooks() {
            /* ajax listener for deleting CSV File from list */
            add_action('wp_ajax_learnpress_export_delete_file', array(__CLASS__, 'ajax_delete_csv_file'));

            /* ajax listener export operation */
            add_action('wp_ajax_learnpress_prepare_course_batches', array(__CLASS__, 'ajax_prepare_batches'));

            /* ajax listener export operation */
            add_action('wp_ajax_learnpress_process_course_batch', array(__CLASS__, 'ajax_process_batch'));

            /* add filter for multi-select-custom quiz type  */
            add_filter( 'LPCE/multi-select-custom' , array( __CLASS__ , 'filter_multi_select_custom') , 1 );

            /* add filter for satisfaction quiz type */
            add_filter( 'LPCE/satisfaction' , array( __CLASS__ , 'filter_satisfaction') , 1 );

        }

        public static function display() {

            $courses = self::get_courses();
            $exports = self::get_exports();

            ?>
            <div class="export-container">
                <div class="col">
                    <h2>Export Course Results:</h2>
                    <table>
                        <tr>
                            <td class="setting-label">

                            </td>
                            <td  class="select-courses">
                                <select id="selected_course">
                                    <?php
                                    foreach ($courses as $key=> $course) {

                                        echo '<option value="'.$course->ID.'">'.$course->post_title.'</option>';
                                    }
                                    ?>
                                </select>
                                <span id="generate-file" class="button button-primary">Generate</span>
                            </td>
                        </tr>
                    </table>
                    <br><br>
                    <div class="confirmation-message hide">
                       Are you sure you want to generate this CSV file? <span id="confirm-yes">[ yes ]</span> <span id="confirm-no">[ no ]</span>
                    </div>
                    <div class="processing-message hide">
                       Gathering & processing batch data. Please do not close your browser until processing is complete.
                    </div>
                    <div class="success-message hide">

                    </div>
                    <div class="nousers-message hide">
                       No users detected
                    </div>
                    <div class="batchreport-container">
                        <ul class="batch-status-list">
                        </ul>
                    </div>


                </div>
                <div class="col available-exports">
                    <h2>CSV Exports:</h2>

                    <?php

                    if($exports['exports']){
                        echo '<ul>';
                        foreach($exports['exports'] as $filename){

                            $file = $exports['learnpress_csv_dir'].$filename;

                            if(!file_exists($file) && !strstr( $filename ,'http' )){
                                continue;
                            }

                            $download_link = $exports['learnpress_csv_url'].$filename;

                             ?>
                             <li>
                                 <a href="<?php echo $download_link; ?>" target="_blank"><?php echo $filename; ?></a>   <span id="delete-file" class="button button-secondarty">Delete</span>
                             </li>
                            <?php
                        }
                        echo '</ul>';
                    } else {
                        echo '<i>No CSV Files Generated</i>';
                    }
                    ?>
                </div>
            </div>
            <?php
        }

        public static function get_courses() {
            $args  = array(
                'post_type'      => array( 'lp_course' ),
                'post_status'    => 'publish',
                'posts_per_page' => - 1
            );

            $courses = get_posts( $args );

            return $courses;
        }

        /**
         * Returns an array upload directory locations and export files contained within
         * @return array
         */
        public static function get_exports( ) {
            $return = array();

            /* get uploads directory */
            $wp_upload_dir = wp_upload_dir();
            $return['base_dir'] = $wp_upload_dir['basedir'];
            $return['learnpress_dir'] = $return['base_dir'].'/learnpress/';

            /* if directory does not exist than create it */
            if (!is_dir($return['learnpress_dir'])) {
                mkdir($return['learnpress_dir'], 0755 , true);
            }

            $return['learnpress_csv_dir'] = $return['learnpress_dir'].'csv-exports/';

            /* if directory does not exist than create it */
            if (!is_dir($return['learnpress_csv_dir'])) {
                mkdir($return['learnpress_csv_dir'], 0755 , true);
            }

            /* make URL version */
            $return['learnpress_csv_url'] = $wp_upload_dir['baseurl'].'/learnpress/csv-exports/';


            /* get uploaded files */
            $return['exports'] =  (file_exists($return['learnpress_csv_dir'])) ? scandir($return['learnpress_csv_dir']) : array() ;

            foreach($return['exports'] as $key=>$filename) {
                /* unset non files */
                if ($filename == '.' || $filename == '..' || $filename == 'index.php' || $filename == 'thumbnail') {
                    unset($return['exports'][$key]);
                }
            }

            return $return;
        }

        public static function ajax_delete_csv_file() {

            /* get uploads directory */
            $wp_upload_dir = wp_upload_dir();
            $base_dir = $wp_upload_dir['basedir'];
            $learnpress_dir = $base_dir.'/learnpress/csv-exports/';

            /* open CSV file */
            self::$csv_file_path = $learnpress_dir.sanitize_text_field($_REQUEST['filename']);

            /* if first run delete csv file created already today */

            unlink(self::$csv_file_path);

            exit;
        }

        public static function ajax_prepare_batches() {
            global $wpdb;

            $return['course_id'] = (int) $_POST['course_id'];

            /* get users who have completed courses */
            $query = "SELECT user_id FROM `".$wpdb->prefix."learnpress_user_items` WHERE `item_id` = '{$return['course_id']}'";
            $users = $wpdb->get_results($query);

            /* prepare counts */
            $return['count'] = count($users);
            $return['limit'] = 20;
            $return['offset'] = 0;
            $return['batches'] = $return['count'] / $return['limit'];

            /* account for sets less than our limit */
            if ($return['batches']<1 || !$return['batches'] ) {
                $return['batches'] = 1;
            }

            echo json_encode($return);
            exit;
        }

        public static function ajax_process_batch() {

            $csv['course_id'] = (int) $_POST['course_id'];
            $csv['limit'] = (int) $_POST['limit'];
            $csv['offset'] = (int) $_POST['offset'];
            $csv['batches'] = (int) $_POST['batches'];

            /* if first offset then prepare CSV file */
            self::prepare_csv_file( $csv );

            /* determine if any batches left */
            if ($csv['offset'] > $csv['batches']) {
                $csv['download'] = self::$csv_file_urlpath;
                $csv['success'] = true;
            }

            /* get users to process */
            $csv['users'] = self::get_users(  $csv );

            /* prepare new CSV row */
            $count = 0;
            //print_r($csv);
            foreach ($csv['users'] as $user) {
                self::prepare_csv_row( $user->user_id , $count , $csv );
                $count++;
            }

            /* close file */
            fclose(self::$csv_file);

            /* increase offset */
            $csv['offset'] = (int) $csv['offset'] + 1;

            /* echo json encoded data */
            echo json_encode($csv);
            exit;
        }

        public static function get_users( $csv ) {
            global $wpdb;

            $true_offset = $csv['offset'] * $csv['limit'];

            /* get users who have completed courses */
            $query = "SELECT user_id FROM `{$wpdb->prefix}learnpress_user_items` WHERE `item_id` = '{$csv['course_id']}' LIMIT {$csv['limit']} OFFSET {$true_offset}";
            $users = $wpdb->get_results($query);

            return $users;
        }


        public static function prepare_csv_file( $csv ) {
            $title = get_the_title( $csv['course_id'] );
            $filename = date("Y.m.d") . '-'.str_replace(array(' ' , ':' , '/' , '\\') , '-' , $title );

            /* get uploads directory */
            $wp_upload_dir = wp_upload_dir();
            $base_dir = $wp_upload_dir['basedir'];
            $learnpress_dir = $base_dir.'/learnpress/csv-exports/';
            $learnpress_url = $wp_upload_dir['baseurl'].'/learnpress/csv-exports/';


            /* open CSV file */
            self::$csv_file_path = $learnpress_dir.$filename.".csv";
            self::$csv_file_urlpath = $learnpress_url.$filename.".csv";

            /* if first run delete csv file created already today */
            if ($csv['offset'] === 0 ) {
                unlink(self::$csv_file_path);
            }

            self::$csv_file = @fopen($learnpress_dir.$filename.".csv","a");

        }

        public static function prepare_csv_row($user_id , $count , $csv ) {

            $field_defaults = array(
                'user_id' => 0,
                'first_name' => '' ,
                'last_name' => '',
                'email address' =>''
            );

            /* get quizes belonging to course */
            $user    = learn_press_get_user($user_id);

            $course = learn_press_get_course( $csv['course_id'] );
            $quizes = $course->get_items('lp_quiz');
            $course_contents = array();

            /* build user details into row */
            $row['user_id'] = $user_id;
            //$user = get_userdata($user_id);
            $row['first_name'] =  $user->get_first_name();
            $row['last_name'] =  $user->get_last_name();
            $row['nickname'] =  $user->get_nickname();
            $row['email'] =  $user->get_email();


            /* look through quizes to add questions */
            foreach ($quizes as $quiz_key => $quiz_id) {

                //$row['quiz_'.$quiz_key.'_id'] = $quiz_id;
                $row['quiz_'.$quiz_key.'_title'] = get_the_title($quiz_id);

                /* get quiz questions */
                $quiz        = LP_Quiz::get_quiz( $quiz_id );
                $course_contents[$quiz_key]['questions']   = $quiz->get_questions();

                /* get user score for quiz */
                $results = $user->get_quiz_results( $quiz_id , $csv['course_id'] , true );
                //print_r($results);
                /* add score to row */
                $row['quiz_'.$quiz_key.'_score'] = $results['result'];

                /* get answers for quiz */
                $answer = self::get_user_history( $user_id , $quiz_id );
                if (is_array($answer) && $answer) {
                    $answer_array =  unserialize($answer[0]->meta_value);
                } else {
                    $answer_array =  array();
                }


                /* get question score & answers  */
                 $i = 0;
                foreach ($course_contents[$quiz_key]['questions'] as $question_id ) {

                    /* get question type */
                    $type = get_post_meta( $question_id, '_lp_type' );
                    $row['quiz_'.$quiz_key.'_question_'.$i.'_type'] = $type[0];

                    /* the there is no answer array for quiz then set answer empty */
                    if (!$answer_array) {
                        $row['quiz_'.$quiz_key.'_question_'.$i.'_answer'] = '';
                        $i++;
                        continue;
                    }

                    /* check to see if question id is in answer array */
                    if (!array_key_exists( $question_id , $answer_array ) ) {
                        $row['quiz_'.$quiz_key.'_question_'.$i.'_answer'] = '';
                        $i++;
                        continue;
                    }

                    $answer_decoded = self::decode_answer($question_id , $answer_array[$question_id] , $type[0]);


                    if (is_array($answer_decoded)) {
                        $answer_decoded = json_encode($answer_decoded);
                    }


                    /* *
                    echo 'answer_decoded';
                    echo "\r\n";
                    echo $answer_decoded;
                    echo "\r\n";
                    /**/

                    $row['quiz_'.$quiz_key.'_question_'.$i.'_answer'] = $answer_decoded;
                    $i++;
                }
            }

            /* look through quizes and related answers to build header columns */
            if ( $csv['offset'] === 0 && $count === 0 ) {
                self::prepare_csv_header( $row );
            }

            /* add user row to CSV file */
            fputcsv(self::$csv_file, $row);

        }


        public static function prepare_csv_header($fields) {
            fputcsv(self::$csv_file, array_keys($fields));
        }

        public static function decode_answer( $question_id , $answer , $status) {
            /* *
            echo "\r\n";
            echo $status;
            echo "\r\n";
            echo $answer;
            echo "\r\n";
            **/

            switch($status) {
                case 'multi-select-custom':
                    return apply_filters('LPCE/multi-select-custom' , $answer );
                    break;
                case 'true_or_false':
                    return $answer;
                    break;
                case 'paragraph':
                    return $answer;
                    break;
                case 'satisfaction':
                    return apply_filters('LPCE/satisfaction' , $answer );
                    break;
            }


            /* if answer is array then discover each answer for given key in array */
            if (is_array($answer) )  {

                /* if is array */
                $answers = array();
                foreach($answer as $key=>$a) {

                    /* if single encoded selection */
                    $answer_rows = self::get_answer_values($a);

                    foreach($answer_rows as $row) {
                        $answer_data = unserialize($row->answer_data);
                        $answers[] = $answer_data['text'];
                    }

                }

                return json_encode($answers);
            }
            /* if answer is not array then get single answer from key */
            else {

                $answer_rows = self::get_answer_values($answer);

                $answer_data =  unserialize($answer_rows[0]->answer_data );
                return $answer_data['text'];
            }



            return $answer;
        }

        public static function get_answer_values( $lookup ) {

            global $wpdb;

            $this_query =  "SELECT *
                FROM {$wpdb->prefix}learnpress_question_answers qa
                WHERE
                qa.answer_data LIKE '%".$lookup."%'
                LIMIT 1
            ";


            return $wpdb->get_results( $this_query );
        }

        public static function get_user_history( $user_id , $question_id ) {

            global $wpdb;
            $query = $wpdb->prepare( "
                SELECT *
                FROM {$wpdb->prefix}learnpress_user_items ui
                LEFT JOIN {$wpdb->prefix}learnpress_user_itemmeta uim ON ui.user_item_id = uim.learnpress_user_item_id
                WHERE ui.user_id = %d
                AND ui.item_id = %d
                AND ui.item_type = %s
                AND uim.meta_key = %s
                ORDER BY ui.user_item_id DESC
                LIMIT 1
            ", $user_id , $question_id, 'lp_quiz' , '_question_answers' );

            return $wpdb->get_results( $query );

        }

        public static function filter_multi_select_custom( $answer ) {

            if (!$answer || !is_string($answer) ) {
                return $answer;
            }

            $answers = json_decode($answer , true);

            $keepers = array();
            /* remove unselected answers */
            foreach( $answers as $key => $answer ) {
                if (!isset($answer['selected']) || $answer['selected'] =='false' ) {
                    continue;
                }

                $keepers[] = trim($answer['value']);
            }


            return json_encode($keepers);
        }

        public static function filter_satisfaction( $answer ) {

            if (!$answer) {
                return $answer;
            }

            $answers = json_decode($answer , true);


            $numbers = array();

            /* remove unselected answers */
            foreach( $answers as $key => $a ) {
                $numbers[$key] = $a['selected'];
            }


            return json_encode($numbers);
        }

    }

    new LearnPress_Export_Course_Data_Settings_Page;


}
