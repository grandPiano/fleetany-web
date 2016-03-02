<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Repositories\UserRepositoryEloquent;
use App\User;
use Log;
use Hash;
use Input;
use Lang;
use Session;
use Redirect;
use Prettus\Validator\Exceptions\ValidatorException;
use Kodeine\Acl\Models\Eloquent\Role;
use App\Repositories\HelperRepository;
use Illuminate\Http\Request;

class UserController extends Controller
{

    protected $userRepo;
    
    protected $fields = [
        'id',
        'name',
        'email',
        'contact-id',
        'company-id',
    ];
    
    public function __construct(UserRepositoryEloquent $userRepo)
    {
        $this->middleware('auth');
        $this->userRepo = $userRepo;
    }

    public function index(Request $request)
    {
        $objHelper = new HelperRepository();
        $filters = $objHelper->getFilters($request->all(), $this->fields, $request);
        
        $users = $this->userRepo->results($filters);
                
        return view("user.index", compact('users', 'filters'));
    }
    
    public function create()
    {
        $user = new User();
        $role = Role::lists('name', 'id');
        
        $role = $role->transform(function ($item) {
            return Lang::get('general.'.$item);
        });
        
        return view("user.edit", compact('user', 'role'));
    }

    public function store()
    {
        try {
            $this->userRepo->validator();
            Input::merge(array('password' => Hash::make(Input::get('password'))));
            $this->userRepo->create(Input::all());
            User::all()->last()->assignRole(Input::get('role_id'));
            Session::flash(
                'message',
                Lang::get(
                    'general.succefullcreate',
                    ['table'=> Lang::get('general.User')]
                )
            );
            return Redirect::to('user');
        } catch (ValidatorException $e) {
            return Redirect::back()->withInput()
                   ->with('errors', $e->getMessageBag());
        }
    }
    
    public function show($idUser)
    {
        $user = $this->userRepo->find($idUser);
        return view("user.show", compact('user'));
    }
    
    public function edit($idUser)
    {
        $user = $this->userRepo->find($idUser);
        $role = Role::lists('name', 'id');
        
        $role = $role->transform(function ($item) {
            return Lang::get('general.'.$item);
        });
        return view("user.edit", compact('user', 'role'));
    }
    
    public function update($idUser)
    {
        try {
            $this->userRepo->validator();
            Input::merge(array('password' => Hash::make(Input::get('password'))));
            $this->userRepo->update(Input::all(), $idUser);
            User::all()->last()->assignRole(Input::get('role_id'));
            Session::flash(
                'message',
                Lang::get(
                    'general.succefullupdate',
                    ['table'=> Lang::get('general.User')]
                )
            );
            return Redirect::to('user');
        } catch (ValidatorException $e) {
            return Redirect::back()->withInput()
                    ->with('errors', $e->getMessageBag());
        }
    }
    
    public function destroy($idUser)
    {
        Log::info('Delete field: '.$idUser);

        if ($idUser != 1 && $this->userRepo->find($idUser)) {
            $this->userRepo->delete($idUser);
            Session::flash('message', Lang::get("general.deletedregister"));
        }
        return Redirect::to('user');
    }
}
