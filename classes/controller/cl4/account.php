<?php defined('SYSPATH') or die('No direct script access.');

class Controller_cl4_Account extends Controller_Base {
	public $page = 'account';

	/**
	* Must be logged in to access any of this controller
	* @var  boolean
	* @see  Controller_Base
	*/
	public $auth_required = TRUE;
	/**
	* The profile and password actions require the account/profile permission
	* @var  array
	* @see  Controller_Base
	*/
	public $secure_actions = array(
		'profile' => 'account/profile',
		'password' => 'account/profile',
	);

	public function before() {
		parent::before();

		$this->add_admin_css();
	} // function before

	/**
	* By default go the profile
	* If the user is not logged in, this will then redirect to the login page
	*/
	public function action_index() {
		Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'profile')));
	} // function

	/**
	* Redirects to the profile action
	*/
	public function action_cancel() {
		Message::add('Your last action was cancelled.', Message::$notice);

		Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'profile')));
	}

	/**
	* Profile edit and save (name and username)
	*/
	public function action_profile() {
		// set the template title (see Controller_Base for implementation)
		$this->template->page_title = 'Profile Edit';

		// get the current user from auth
		$user = Auth::instance()->get_user();
		// use the user loaded from auth to get the user profile model (extends user)
		$model = ORM::factory('user_profile', $user->pk());

		if ( ! empty($_POST) && ! empty($_POST['form']) && $_POST['form'] == 'profile') {
			try {
				// store the post values
				$model->save_values();
				// the user no longer is forced to update their profile
				$model->force_update_profile_flag = FALSE;
				// save first, so that the model has an id when the relationships are added
				$model->save();
				// message: profile saved
				Message::add(__(Kohana::message('account', 'profile_saved')), Message::$notice);

				// reload the user in the session
				Auth::instance()->get_user()->reload();

				// redirect because they have changed their name, which is displayed on the page
				Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'profile')));

			} catch (ORM_Validation_Exception $e) {
				Message::message('account', 'profile_save_validation', array(
					':validation_errors' => Message::add_validation_errors($model->validation(), 'user')
				), Message::$error);

			} catch (Exception $e) {
				Kohana_Exception::caught_handler($e);
				Message::add(__(Kohana::message('account', 'profile_save_error')), Message::$error);
			}
		} // if

		// use the user loaded from auth to get the user profile model (extends user)
		$model->set_options(array(
			'display_reset' => FALSE,
			'hidden_fields' => array(
				Form::hidden('form', 'profile'),
			),
		));

		// prepare the view & form
		$this->template->body_html = View::factory('cl4/cl4account/profile')
			->set('edit_fields', $model->get_form(array(
				'form_action' => URL::site(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'profile'))),
				'form_id' => 'editprofile',
			)));
	} // function action_profile

	/**
	* Saves the updated password and then calls action_profile() to generate form
	*/
	public function action_password() {
		$this->template->page_title = 'Change Password';

		// get the current user from auth
		$user = Auth::instance()->get_user();

		if ( ! empty($_POST) && ! empty($_POST['form']) && $_POST['form'] == 'password') {
			$model = ORM::factory('user_password', $user->pk());
			$user_rules = $model->rules();
			$labels = $model->labels();

			$validation = Validation::factory($_POST)
				->labels(array(
					'current_password' => 'Current ' . $labels['password'],
					'new_password' => 'New ' . $labels['password'],
					'new_password_confirm' => 'Confirm New ' . $labels['password'],
				))
				->rules('current_password', $user_rules['password'])
				->rules('new_password', $user_rules['password'])
				->rules('new_password_confirm', array(array('matches', array(':validation', 'new_password', 'new_password_confirm'))));
			$validation->check();
			$errors = $validation->errors();

			if (empty($errors)) {
				if (Kohana::$config->load('auth.enable_3.0.x_hashing')) {
					if (Auth::instance()->hash_password((string) $validation['current_password'], Auth::instance()->find_salt($user->password)) !== $user->password) {
						$validation->error('current_password', 'not_the_same');
					}
				} else {
					if (Auth::instance()->hash((string) $validation['current_password']) !== $user->password) {
						$validation->error('current_password', 'not_the_same');
					}
				}

				// repopulate the error array
				$errors = $validation->errors();
			}

			// check if there are any errors
			if (empty($errors)) {
				try {
					$model = ORM::factory('user_password', $user->pk())
						->values(array(
							'password' => $_POST['new_password'],
							// user no longer needs to update their password
							'force_update_password_flag' => FALSE,
						))
						->save();

					Message::add(__(Kohana::message('account', 'password_changed')), Message::$notice);

					// reload the user in the session
					Auth::instance()->get_user()->reload();

					// redirect and exit
					Request::current()->redirect(Route::get(Route::name(Request::current()->route()))->uri(array('action' => 'profile')));

				} catch (Exeception $e) {
					Kohana_Exception::caught_handler($e);
					Message::add(__(Kohana::message('account', 'password_change_error')), Message::$error);
				}

			} else {
				Message::add(__(Kohana::message('account', 'password_change_validation')) . Message::add_validation_errors($validation, 'account'), Message::$error);
			}
		} // if

		// call action profile to generate the profile page with both username and email plus password fields
		$this->action_profile();
	} // function action_password
} // class