<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of loan
 *
 * @author sochy.choeun
 */
class loan extends CI_Controller {

    //put your code here
    var $data = NULL;
    var $rand = NULL;

    function __construct() {
        parent::__construct();
        $this->load->model(array('m_loan_product_type', 'm_loan', 'm_saving', 'm_global', 'global/mod_global'));
//        $this->rand=NULL;
    }

    function index() {
        redirect('loan/open');
    }

    function add() {
        // find loan with cid // This customer ready have loan account not yet close.
        $exit_loan = $this->m_global->select_where("loan_account", array('loa_acc_con_id' => $this->input->post('con_cid'), 'loa_status' => 0));

        if ($exit_loan->num_rows() > 0) {
            $this->session->set_flashdata('error', '<div class="alert alert-error">This custormer ready have loan account.</div>');
            redirect('loan/open');
        }

        $arr_pro_id = $this->m_global->select_where("loan_product_type", array('loa_pro_typ_id' => $this->input->post('loa_acc_loa_pro_typ_id')), 1);
        if ($arr_pro_id->num_rows() > 0) {
            foreach ($arr_pro_id->result() as $arr_data) {
                $pro_type_code = $arr_data->loa_pro_typ_code;
            }
        }
        $last_id = $this->m_loan->add($pro_type_code);



        if ($last_id > 0) {
//            =============== Create repayment schedule==================
            if ($this->repayment_schedule($last_id)) {
                $this->session->set_flashdata('success', 'A loan account has been saved');
            }
//            ======================end repayment ========================

            $this->session->set_userdata(array('loa_id' => $last_id));

            redirect('loan/voucher');
        } else {
//            $this->session->set_flashdata('error', 'Error to create loan');
            redirect('loan/Open');
        }
//
    }

    function edit() {
        $last_update = $this->m_loan->edit($this->input->post('account_number'));
        if ($last_update) {
            $loan_id = $this->input->post('loa_con_id');
            $this->m_global->delete('repayment_schedule', array('rep_sch_loa_acc_id' => $loan_id));
//            =============== Create repayment schedule==================
            if ($this->repayment_schedule($loan_id)) {
                $this->session->set_flashdata('success', 'A loan account has been saved');
            }
//            ======================end repayment ========================

            $this->session->set_userdata(array('loa_id' => $loan_id)); /// For view in vourcher
            $this->session->set_userdata(array('loa_code' => $this->input->post('account_number'))); // Add loand code for view
            redirect('loan/voucher');
        } else {
            $this->session->set_flashdata('error', 'Error to create loan');
            redirect('loan/open');
        }
    }

    function calculate_interest() {
        $data = null;

        $loan_amount = str_replace(",", "", $this->input->post('loan_amount'));
//        $loan_amount = $this->input->post('loan_amount'); // Loan amount start up

        $rate_per = $this->input->post('interest_rate') / 100; // Percentag of interest %
        $loan_peraid = $this->input->post('num_installments'); // Number for time to repayment
        //1 ==============instalment====================
        $data ['instalment'] = round(($loan_amount * $rate_per) / (1 - pow((1 + $rate_per), (-$loan_peraid))), -2);

        echo json_encode($data);
    }

    function voucher($loan_id = NULL) {
        $loa_cod = $this->session->userdata('loa_code');
        $this->data['title'] = 'Loan disbursment voucher';

        $loan_info = $this->m_global->select_join('loan_account', array('contacts' => array('loa_acc_con_id' => 'con_id'),
            'loan_installment' => array('loa_acc_id' => 'loa_ins_loa_acc_id'),
            'loan_product_type' => array('loa_acc_loa_pro_type_code' => 'loa_pro_typ_id'),
//            'gl_list' => array('loa_acc_gl_code' => 'gl_code'), /// Not use for this time
             'loan_account_type' => array('loa_lat_id' => 'lat_id'),
            'currency' => array('loa_acc_cur_id' => 'cur_id'),
            'users' => array('loa_acc_use_id' => 'use_id'),
            'loan_detail' => array('loa_acc_loa_det_id' => 'loa_det_id'),
            'repayment_freg' => array('loa_acc_rep_fre_id' => 'rep_fre_id')), 'inner', array('loan_account.loa_acc_code' => $loa_cod), '1');

        $this->data['loan_info'] = $loan_info;
        $con_info = NULL;
        if ($loan_info->num_rows() > 0) {
            foreach ($loan_info->result() as $row) {
                $con_info = $this->m_loan->find_contact_by_code($row->con_cid);
            }
        }

        if ($con_info != NULL) {
            //foreach ($con_info->result() as $row_con) {
            $this->data['con_info'] = $con_info;
            //}
        }

        $this->load->view("loan/voucher", $this->data);
    }

    function schedule($loa_acc_id = NULL) {
        $loa_acc_id = $this->session->userdata('loa_id'); //Loan id from open/edit, and view loan form
        $loa_acc_code = $this->session->userdata('loa_code');

        $this->data['sum_result_query'] = $this->m_global->select_count('repayment_schedule', array('rep_sch_saving' => 'sav_total',
            'rep_sch_rate_repayment' => 'rate_total',
            'rep_sch_total_repayment' => 'total_repayment',
            'rep_sch_principle_amount_repayment' => 'total_principle_amount_repayment'), array('rep_sch_loa_acc_id' => $loa_acc_id));


        $this->data['title'] = 'Loan disbursment Schedule';

        $loan_info = $this->m_global->select_join('loan_account', array('contacts' => array('loa_acc_con_id' => 'con_id'),
            'loan_installment' => array('loa_acc_id' => 'loa_ins_loa_acc_id'),
            'loan_product_type' => array('loa_acc_loa_pro_type_code' => 'loa_pro_typ_id'),
//            'gl_list' => array('loa_acc_gl_code' => 'gl_code'),
            'loan_account_type' => array('loa_lat_id' => 'lat_id'),
            'currency' => array('loa_acc_cur_id' => 'cur_id'),
            'users' => array('loa_acc_use_id' => 'use_id'),
            'loan_detail' => array('loa_acc_loa_det_id' => 'loa_det_id'),
            'repayment_freg' => array('loa_acc_rep_fre_id' => 'rep_fre_id')), 'inner', array('loan_account.loa_acc_code' => $loa_acc_code), '1');

        $this->data['loan_info'] = $loan_info;
        $con_info = NULL;
        if ($loan_info->num_rows() > 0) {
            foreach ($loan_info->result() as $row) {
                $con_info = $this->m_loan->find_contact_by_code($row->con_cid);
            }
        }
//===============View sample for tesing
//        if ($con_info != NULL) {
//            foreach ($con_info->result() as $row_con) {
//            $this->data['con_info'] = $con_info;
//            }
//        }
//        =====================
//        
        if ($con_info != NULL) {
            //foreach ($con_info->result() as $row_con) {
            $this->data['con_info'] = $con_info;
            //}
        }

        $this->data['repayment_sch'] = $this->m_global->select_where('repayment_schedule', array('rep_sch_loa_acc_id' => $loa_acc_id));

        $this->load->view("loan/schedule", $this->data);
    }

    function repayment_schedule($get_acc_id = NULL, $repay_type = NULL) {

//        ========Test sample data=========
//           $repay_type = 1; //1:Anurity  2:Declining
//          $loan_amount = 10000;
//          $rate_per = 1.2 / 100;
//          $loan_peraid = 36;
//          $num_date = 30;
//          $instalment =  343.72 ;
//          $loa_id = 11;
//=================end=============
        //        =====Get user data input================
        $repay_type = $this->input->post('loa_sch_id');
        $loan_amount = str_replace(",", "", $this->input->post('loan_amount')); // Loan amount start up
        $rate_per = $this->input->post('interest_rate') / 100; // Percentag of interest %
        $loan_peraid = $this->input->post('num_installments'); // Number for time to repayment
        $num_date = $this->input->post('rep_freg'); // Type of repayment ex: Monthly, Daily, Weekly
        $instalment = $this->input->post('ins_amount');
        $loa_id = $get_acc_id;
        $disbu_date = $this->input->post('disbursment_date');

//        ============Totals=================
        $t_rate = $t_principle = $t_balance = $t_pay = 0;

        //==============The number of freg select from DB=================
//        $arr_num_freg = $this->m_global->select_where("repayment_freg", array('rep_fre_id' => $rep_freg), 1);
//        if ($arr_num_freg->num_rows() > 0) {
//            foreach ($arr_num_freg->result() as $arr_data) {
//                $num_date = $arr_data->rep_fre_period;
//            }
//        }
//
//        $num_date = $rep_freg;
        //1 ==============instalment====================
        $instalment = ($loan_amount * $rate_per) / (1 - pow((1 + $rate_per), (-$loan_peraid)));
        $instalment = round($instalment, -2);
        //          ======================= Repayment day ===========================
        $repayment_date = $disbu_date;
//        ///=====sample data==========
//        $repayment_date = date('Y-m-d', $repayment_date . "+" . $num_date . " days");
        //==============variable for repayment type Anuity
        $arr_sch = array();
        $principle_repay = 0;
        $rate_repay = 0;
        $last_priciple = $loan_amount;
        $tmp_balance = $loan_amount;
//
//        echo "<table border='1'><tr>";
//        echo "<td>principle_repay</td><td>Rate</td><td>Total repay</td><td>Last priciple</td><td>Key</td>";

        for ($i = 1; $i <= $loan_peraid; $i++) {
            $repayment_date = date('Y-m-d', strtotime($repayment_date . "+" . $num_date . " days"));
            if ($repay_type == 1) { //Anuity repayment type
                ////////========================Anuity schedul=====================================
                //          1 ===================Rate===============
                $rate = round($rate_per * $last_priciple, 0);
                //          2 ================ Principle repayment ===============
                $principle_repay = round($instalment - $rate, 0);
                //            ================ total repayment =============
                $total_repayment = round($principle_repay + $rate, 0);
            } else if ($repay_type == 2) { // Declining repayment type
                $rat_pric = ($loan_amount / $loan_peraid / $loan_amount);  // Rat of priciple pay
                //           1 ==============Key=====================
                $key_culum = round(($loan_amount * $rat_pric), 0);
                //           2 =============== principle repay ================
                $principle_repay = $key_culum;

                //           3 ===================Rate===============
                $rate = round(($rate_per * $last_priciple), 0);
                //           4 ================ total repayment =============
                $total_repayment = round($principle_repay + $rate, 0);
                //           5 ============= Last priciple amount =======
                $last_priciple -=$principle_repay;
            }

//           // =====Console round up function ===============
            $tmp_balance -= round($principle_repay, 0);

            if ($tmp_balance >= 0) {
                $last_priciple = $tmp_balance;
            } else {
                $total_repayment = $total_repayment + $tmp_balance;
                if ($repay_type == 2) {  // Declining repayment type
                    $principle_repay += round($tmp_balance, 0);
                } else {
                    $principle_repay = round($last_priciple, 0);
                }
                $last_priciple = 0;
            }
//============Inser to database=======================
            $arr_sch_rec = array(
                'rep_sch_num' => $i,
                'rep_sch_date_repay' => $repayment_date,
                'rep_sch_principle_amount_repayment' => $principle_repay,
                'rep_sch_rate_repayment' => $rate,
                'rep_sch_total_repayment' => $total_repayment,
                'rep_sch_balance' => $last_priciple,
                'rep_sch_instalment' => $instalment,
                'rep_sch_loa_acc_id' => $loa_id,
                'rep_sch_status' => 1
            );
            array_push($arr_sch, $arr_sch_rec);
        }
        $this->db->insert_batch('repayment_schedule', $arr_sch);
        //======================//===========================
        //            ============View sample data================
//            echo '</tr><tr><td>' . $i . '</td><td>' . $principle_repay . '</td><td>' . $rate .
//            '</td><td>' . $total_repayment . '</td><td>' . $last_priciple .
//            '</td><td>' . $instalment . '</td></tr>';
        //        //=======Totals Sample ============
//          $t_rate = $t_rate + $rate;
//          $t_pay = $t_pay + $total_repayment;
//          $t_principle = $t_principle + $principle_repay;
//          ===============End view sample data==================
//        echo "</tr><tr><td></td><td>" . $t_principle . '</td><td>' . $t_rate .
//        '</td><td>' . $t_pay . '</td><td></td><td></td></tr>';
//
//        echo '</table>';
//        exit(); 
        return TRUE;
//        ============End sample================
        //        ==== Sent to view===============
//        $this->data['repayment_sch'] = $this->m_global->select_where('repayment_schedule', array('rep_sch_loa_acc_id' => 11));
//        $this->load->view(Variables::$layout_main, $this->data);
        // ===================Sample datab input=============
        /*
          $repay_type = 1; //1:Anurity  2:Declining
          $loan_amount = 10000;
          $rate_per = 1.2 / 100;
          $loan_peraid = 36;
          $num_date = 30;
          $instalment =  343.72 ;
          $loa_id = 11;
          //        =====Get user data input================
          //        $repay_type = $this->input->post('loa_sch_id');
          //        $loan_amount = $this->input->post('loan_amount'); // Loan amount start up
          //        $rate_per = $this->input->post('interest_rate') / 100; // Percentag of interest %
          //        $loan_peraid = $this->input->post('num_installments'); // Number for time to repayment
          //        $num_date = $this->input->post('rep_freg'); // Type of repayment ex: Monthly, Daily, Weekly
          //        $instalment = $this->input->post('ins_amount');
          //        $loa_id = $get_acc_id;

          $disbu_date = $this->input->post('disbursment_date');

          //          ======================= Repayment day ===========================
          $repayment_date = $disbu_date;
          //variable for repayment type Anuity
          $arr_sch = array();
          $principle_repay = 0;
          $rate_repay = 0;
          $last_priciple = $loan_amount;
          $tmp_balance = $loan_amount;
          //        ============Totals=================
          $t_rate = $t_principle = $t_balance = $t_pay = 0;

          echo "<table border='1'><tr>";
          echo "<td>No</td><td>principle_repay</td><td>Rate</td><td>Total repay</td><td>Last priciple</td><td>Key</td>";
          for ($i = 1; $i <= $loan_peraid; $i++) {
          $repayment_date = date('Y-m-d', strtotime($repayment_date . "+" . $num_date . " days"));
          if ($repay_type == 1) { //Anuity repayment type
          ////////========================Anuity schedul=====================================
          //          1 ===================Rate===============
          $rate = round($rate_per * $last_priciple, 0);
          //                $rate = $rate_per * $last_priciple;

          //          2 ================ Principle repayment ===============
          $principle_repay = round($instalment - $rate, 0);
          //                $principle_repay = $instalment - $rate;

          //            ================ total repayment =============
          $total_repayment = round($principle_repay + $rate,0);
          //                $total_repayment = $principle_repay + $rate;


          } else if ($repay_type == 2) { // Declining repayment type
          $rat_pric = ($loan_amount / $loan_peraid / $loan_amount);  // Rat of priciple pay
          //            $key_culum = $loan_amount - ($loan_amount * $rat_pric *(-1));
          //           1 ==============Key=====================
          $key_culum = round(($loan_amount * $rat_pric), 0);
          //                $key_culum = $loan_amount * $rat_pric;
          //           2 =============== principle repay ================
          $principle_repay = $key_culum;

          //           3 ===================Rate===============
          $rate = round(($rate_per * $last_priciple), 0);
          //                $rate = $rate_per * $last_priciple;
          //           4 ================ total repayment =============
          $total_repayment = round($principle_repay + $rate,0);
          //                $total_repayment = $principle_repay + $rate;
          //           5 ============= Last priciple amount =======
          $last_priciple -=$principle_repay;
          }

          //           // =====Console round up function ===============
          $tmp_balance -= round($principle_repay, 0);
          //            $tmp_balance -= $principle_repay;

          if ($tmp_balance >= 0) {
          $last_priciple = $tmp_balance;
          } else {
          $total_repayment = $total_repayment + $tmp_balance;
          if ($repay_type == 2) {  // Declining repayment type
          $principle_repay += round($tmp_balance, 0);
          //                    $principle_repay += $tmp_balance;
          } else {
          $principle_repay = round($last_priciple, 0);
          //                    $principle_repay = $last_priciple;
          }
          $last_priciple = 0;
          }

          //            ============View sample data================
          echo '</tr><tr><td>' . $i . '</td><td>' . $principle_repay . '</td><td>' . $rate .
          '</td><td>' . $total_repayment . '</td><td>' . $last_priciple .
          '</td><td>' . $instalment . '</td></tr>';


          //        //=======Totals ============
          $t_rate = $t_rate + $rate;
          $t_pay = $t_pay + $total_repayment;
          $t_principle = $t_principle + $principle_repay;
          }

          echo "</tr><tr><td></td><td>" . $t_principle . '</td><td>' . $t_rate .
          '</td><td>' . $t_pay . '</td><td></td><td></td></tr>';

          echo '</table>';

          exit();
          return TRUE;

         */
//        ==== Sent to view===============   
//        $this->data['repayment_sch'] = $this->m_global->select_where('repayment_schedule', array('rep_sch_loa_acc_id' => 11));
//        $this->load->view(Variables::$layout_main, $this->data);
        //======================End sample data===================//
    }

    function repayment_schedule_excel($get_acc_id = NULL) {

        $loan_amount = $this->input->post('loan_amount'); // Loan amount start up
        $rate_per = $this->input->post('interest_rate') / 100; // Percentag of interest %
        $loan_peraid = $this->input->post('num_installments'); // Number for time to repayment
        $rep_freg = $this->input->post('rep_freg'); // Type of repayment ex: Monthly, Daily, Weekly
        $disbu_date = $this->input->post('disbursment_date');
        $loa_id = $get_acc_id;


        $arr_num_freg = $this->m_global->select_where("repayment_freg", array('rep_fre_id' => $rep_freg), 1);
        if ($arr_num_freg->num_rows() > 0) {
            foreach ($arr_num_freg->result() as $arr_data) {
                $num_date = $arr_data->rep_fre_period;
            }
        }
        //1 ==============instalment====================
        $instalment = ($loan_amount * $rate_per) / (1 - pow((1 + $rate_per), (-$loan_peraid)));

//          ======================= Repayment day ===========================
        $repayment_date = $disbu_date;
        //$repayment_date = date('Y-m-d', $repayment_date."+" . $num_date . " days");

        $arr_sch = array();
        $principle_repay = 0;
        $rate_repay = 0;
        $balance = $loan_amount;
        for ($i = 1; $i <= $loan_peraid; $i++) {
            $repayment_date = date('Y-m-d', strtotime($repayment_date . "+" . $num_date . " days"));

//          1 ===================Rate===============
            $rate = $rate_per * $balance;

//          2 ================ Principle repayment ===============
            $principle_repay = $instalment - $rate;

//            ================ total repayment =============
            $total_repayment = $principle_repay + $rate;
//            ================ balance after repay==================
            $balance -= $principle_repay;

            $arr_sch_rec = array(
                'rep_sch_num' => $i,
                'rep_sch_date_repay' => $repayment_date,
                'rep_sch_principle_amount_repayment' => $principle_repay,
                'rep_sch_rate_repayment' => $rate,
                'rep_sch_total_repayment' => $total_repayment,
                'rep_sch_balance' => $balance,
                'rep_sch_instalment' => $instalment,
                'rep_sch_loa_acc_id' => $loa_id
            );
            array_push($arr_sch, $arr_sch_rec);
        }
        $this->db->insert_batch('repayment_schedule', $arr_sch);
        return TRUE;
    }

    //////==============Get table repayment==============================
    function repayment_tbl($loa_id = NULL) {
//        return "Hello";
        $this->data['repayment_sch'] = $this->m_global->select_where('repayment_schedule', array('rep_sch_loa_acc_id' => $loa_id));
//        $this->data['repayment_sch'] = $this->m_global->select_where('repayment_schedule',array('rep_sch_loa_acc_id' =>35));
//        return $this->load->view('loan/repayment_schedule', $this->data);
        $arr_field_sch_table = array(
            'ល.រ' => 'rep_sch_num',
            'ថ្ចៃសងប្រាក់' => 'rep_sch_date_repay', // Due date
            'ប្រាក់ដើម' => 'rep_sch_principle_amount_repayment', // Principal
            'ការប្រាក់' => 'rep_sch_rate_repayment', //Interest
            'ប្រាក់ដើមនៅសល់' => 'rep_sch_balance', //Outstanding
        );
        return table_manager($this->data['repayment_sch'], $arr_field_sch_table, FALSE, 1);
    }

    //======================================================================
    function block() {
        $this->data['title'] = 'Page blocked..!';
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function open() {
//        echo $this->session->userdata('loa_code');exit();

        $this->data['title'] = 'Open loan account';

        $contracts = $this->m_saving->get_contacts();
        if ($contracts == NULL) {
            $this->session->set_flashdata('error', '<div class="alert alert-error">Contract is empty, please add contract first.</div>');
            redirect('loan/block');
        }

        $this->data['edit'] = 0;

        if (segment(3) == TRUE && segment(3) == "edit") {
            $this->data['edit'] = 1;
            $this->data['title'] = 'Edit loan account';
        }
        $this->data['acc_num_query'] = $this->m_global->select('loan_account', array('loa_acc_code'));

        allows(array(Setting::$role0, Setting::$role1));

        $product_type = $this->m_loan_product_type->get_loan_product_type_array();
        $this->data['product_type'] = $product_type;
        $contracts = $this->m_loan->get_contacts();
//        $gl = $this->m_loan->find_gl_code_for_dropdown(); ////////  Not need for this time
        $rep_peraid = $this->m_loan->rep_peraid();
        $this->data['rep_peraid'] = $rep_peraid;

        $currency = $this->m_loan->find_currencies_for_dropdown();
        $this->data['contacts'] = $contracts;
        $this->data['currency'] = $currency;
//        $this->data['gl'] = $gl; ////////  Not need for this time
          $this->data['loan_account_type'] = $this->m_loan->laon_account_type_for_dropdown();
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function openloan() {
//        $this->rand = 10;
        $random_code = random_string('alnum', 16);
        $this->data['random_code'] = $random_code;
        $this->data['title'] = '(Dis)approve Loan Application';
        $this->data['acc_num_query'] = $this->m_global->select('loan_account', array('loa_acc_code'));
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function viewloan() {
        $random_code = random_string('alnum', 16);
        $this->data['random_code'] = $random_code;
        $this->data['title'] = 'View Loan infomation';
        $this->data['acc_num_query'] = $this->m_global->select('loan_account', array('loa_acc_code'));
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function loan_status() {
//        echo $this->rand."<br />Ok";
//        echo $this->input->post("l_id_code"); exit();
        if ($this->input->post("l_id_code") == TRUE && $this->input->post("btn_nam") == TRUE) {
            $result = $this->m_loan->update_loan_approve($this->input->post("l_id_code"), $this->input->post("btn_nam"));
            if ($result == TRUE && $result != NULL) {
                echo $result;
            } else {
                echo 'Disapproved';
            }
            $this->session->set_flashdata('success', 'A loan account has been ' . $this->input->post("btn_nam"));
//            redirect('loan/Openloan#contents');
        } else {
            return FALSE;
        }
    }

    function lists() {
        allows(array(Setting::$role0, Setting::$role1));
        $this->data['title'] = 'List loan accounts';
        $this->data['loan_account'] = $this->m_loan->get_loan_account();
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function test() {
        $contact_info = $contact_info = $this->m_global->select_join('loan_account', array(
            'contacts' => array('loa_acc_con_id' => 'con_id'),
            'loan_installment' => array('loa_acc_id' => 'loa_ins_loa_acc_id'),
            'loan_product_type' => array('loa_acc_loa_pro_type_code' => 'loa_pro_typ_id')), 'inner', array('loan_account.loa_acc_code' => "777-000001-01-1"), '1');
//       
        echo $contact_info;
    }

    function find_contact_by_code() {
        allows(array(Setting::$role0, Setting::$role1));
        $data = null;

//        $exit_loan = $this->m_global->select_where("loan_account",array('loa_acc_con_id'=>$this->input->post('con_cid'),'loa_status'=>0));
//        if($exit_loan->num_rows()> 0){
//            $this->session->set_flashdata('error', '<div class="alert alert-error">This custormer ready have loan account.</div>');
//            redirect('loan/open');
//        }

        if ($this->input->post('acc_num') != NULL) {
//            $contact_info = $this->m_global->select_join('loan_account', array('contacts' => array('loa_acc_con_id' => 'con_id'), 'loan_installment' => array('loa_acc_id' => 'loa_ins_loa_acc_id')), 'inner', array('loan_account.loa_acc_code' => $this->input->post('acc_num')), '1');
            $contact_info = $this->m_global->select_join('loan_account', array('contacts' => array('loa_acc_con_id' => 'con_id'),
                'loan_installment' => array('loa_acc_id' => 'loa_ins_loa_acc_id'),
                'loan_product_type' => array('loa_acc_loa_pro_type_code' => 'loa_pro_typ_id'),
                'gl_list' => array('loa_acc_gl_code' => 'gl_code'),
                'currency' => array('loa_acc_cur_id' => 'cur_id'),
                'loan_detail' => array('loa_acc_loa_det_id' => 'loa_det_id'),
                'repayment_freg' => array('loa_acc_rep_fre_id' => 'rep_fre_id')), 'inner', array('loan_account.loa_acc_code' => $this->input->post('acc_num')), '1');

            if ($contact_info->num_rows() > 0) {

                foreach ($contact_info->result() as $row) {
                    $contact_id = $row->con_cid;
                    $data['loa_acc_id'] = $row->loa_acc_id;
                    $data['pro_type'] = $row->loa_acc_loa_pro_type_code;
                    $data['pro_type_code'] = $row->loa_pro_typ_code . " : " . $row->loa_pro_typ_amount;
                    $data['gl'] = $row->loa_acc_gl_code;
                    $data['gl_des'] = $row->gl_description;
                    $data['currency'] = $row->loa_acc_cur_id;
                    $data['currency_title'] = $row->cur_title;
                    $data['loa_amount'] = formatMoney($row->loa_acc_amount, TRUE);
                    $data['loa_acc_disbustment'] = $row->loa_acc_disbustment;
                    $data['loa_acc_rep_fre_id'] = $row->loa_acc_rep_fre_id;
                    $data['loa_acc_rep_fre_type'] = $row->rep_fre_type;
                    $data['loa_acc_first_repayment'] = $row->loa_acc_first_repayment;
                    $data['loa_ins_num_ins'] = $row->loa_ins_num_ins;
                    $data['loa_ins_lead_interest'] = $row->loa_ins_lead_interest;
                    $data['loa_ins_principal_start'] = $row->loa_ins_principal_start;
                    $data['loa_ins_principal_frequency'] = $row->loa_ins_principal_frequency;
                    $data['loa_ins_interest_rate'] = $row->loa_ins_interest_rate;
                    $data['loa_ins_installment_amount'] = $row->loa_ins_installment_amount;
                    $data['create_date'] = $row->loa_acc_created_date;
//                    $data['loa_exit'] = $row->loa_acc_created_date;
                    $data['loa_det_status'] = $row->loa_det_status;
                    $data['loa_acc_loa_det_id'] = $row->loa_acc_loa_det_id;


                    $data['tbl_rep'] = $this->repayment_tbl($row->loa_acc_id);

                    $contact = $data;


                    $this->session->set_userdata(array('loa_id' => $row->loa_acc_id)); /// For view in vourcher
                    $this->session->set_userdata(array('loa_code' => $this->input->post('acc_num'))); // Add loand code for view
                }
                $contact += $this->m_loan->find_contact_by_code($contact_id);
            } else {
                $contact_id = 0;
                $contact = $this->m_loan->find_contact_by_code($contact_id);
            }
        } else {
            $contact_id = $this->input->post('con_cid');
            $contact = $exit_loan = $this->m_loan->exit_loa_of_contact(); //check if contact have a loan ready need to close the previese first.

            $contact += $this->m_loan->find_contact_by_code($contact_id);
        }

        if ($contact != NULL) {
            echo json_encode($contact);
        } else {
            echo json_encode(array('result' => 0));
        }
    }

    function find_loan_by_contact_id() {
        allows(array(Setting::$role0, Setting::$role1));
        $contact = $this->m_loan->find_contact_by_code($this->input->post('con_cid'));
        if ($contact != NULL)
            echo json_encode($contact);
        else
            echo json_encode(array('result' => 0));
    }

    function find_gl_by_product_type_id() {
        allows(array(Setting::$role0, Setting::$role1));
        $contact = $this->m_loan->find_gl_by_product_type_id($this->input->post('id'));
        if ($contact != NULL)
            echo json_encode($contact);
        else
            echo json_encode(array('result' => 0));
    }

    function delete() {
        allows(array(Setting::$role0, Setting::$role1));
        if ($this->m_loan->delete_loan_account_by_id()) {
            $this->session->set_flashdata('success', 'Saving account(s) has been deleted.');
            redirect('loan/lists');
        } else {
            $this->session->set_flashdata('error', 'Saving account(s) could not deleted');
            redirect('loan/lists');
        }
    }

    function view() {
        $this->data['title'] = 'View loan account';
        $this->data['contacts'] = $this->m_loan->get_contacts_loan();
        $this->load->view(Variables::$layout_main, $this->data);
    }

    function search() {
        $data['title'] = 'Search loan account';
        $data['acc_num_query'] = $this->m_global->select('loan_account', array('loa_acc_code'));
        $this->load->view(Variables::$layout_main, $data);
    }

    function search_account() {
        $query_data = $this->m_global->select('loan_account', array('loa_acc_code'));
        return $data;
    }

}

?>
