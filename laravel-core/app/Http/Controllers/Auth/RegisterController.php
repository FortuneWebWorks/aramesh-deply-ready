<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Practitioner;
use App\Providers\RouteServiceProvider;
use App\Models\User;
use App\Models\VerificationCode;
use Carbon\Carbon;
use Faker\Core\Number;
use Ghasedak\GhasedakApi;
use Illuminate\Foundation\Auth\RegistersUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Illuminate\Database\Eloquent\Model;

class RegisterController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Register Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles the registration of new users as well as their
    | validation and creation. By default this controller uses a trait to
    | provide this functionality without requiring any additional code.
    |
    */

    use RegistersUsers;

    /**
     * Where to redirect users after registration.
     *
     * @var string
     */
    protected $redirectTo = '/dashboard';

    public function redirectTo() {
        $user = User::where('phone-number', Auth::user()['phone-number'])->first();
        $practitioner = Practitioner::where('phone-number', Auth::user()['phone-number'])->first();

        if(!$user && $practitioner) {
            $this->redirectTo = '/admin/dashboard';
            return '/admin/dashboard';
        }
        $this->redirectTo = '/dashboard';
        return '/dashboard';
    }

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    /**
     * Get a validator for an incoming registration request.
     *
     * @param  array  $data
     * @return \Illuminate\Contracts\Validation\Validator
     */
    protected function validator(array $data)
    {
        return Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
    }

    /**
     * Create a new user instance after a valid registration.
     *
     * @param  array  $data
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function create()
    {
        $practitioners = Practitioner::where('name', request()->input('data')['advisor'])->first()->users()->create([
            'name' => request()->input('data')['name'],
            'phone-number' => request()->input('data')['phoneNumber'],
            'both-parents' => request()->input('data')['bothParents'] === "false" ? false : true
        ]);

        foreach(request()->input('data')['familyData'] as $member) {
            $practitioners->members()->create([
                'role' => $member['role'] ,
                'date-of-birth' => $member['birthDate']
            ]);
        }
        return Redirect::route('login')->with([
            'message' => 'حساب کاربری شما با موفقیت ساخته شد. لطفا وارد حساب کاربری خود شوید',
            'phone-number' => request()->input('data')['phoneNumber']
        ]);
    }

    protected function confirmation(Request $request, $phoneNumber)
    {
        $user = User::where('phone-number', $phoneNumber)->first();
        $practitioner = Practitioner::where('phone-number', $phoneNumber)->first();
        
        if($user) {
            $prevCode = $user->verificationCodes()->where('expires_at', '>', Carbon::now())->first();
            
            if(!$prevCode) {
                $code = $this->makeVerificationCode($user);
                $api = new GhasedakApi(env('GHASEDAKAPI_KEY'));
                $api->SendSimple(
                    "0".$phoneNumber,  // receptor
                    "کد احراز هویت شما در وبسایت آرامش \n " . $code, // message
                    "2000235"    // choose a line number from your account
                    );
                return Inertia::render('auth/UserConfirm', [
                    'phoneNumber'=> $phoneNumber,
                    'nextRoute' => request()->input('nextRoute'),
                ]);
            } else {
                return Inertia::render('auth/UserConfirm', [
                    'phoneNumber'=> $phoneNumber,
                    'nextRoute' => request()->input('nextRoute'),
                    'errors'=> array(0 => "کد فعالسازی قبلا برای شما ارسال شده است لطفا دو دقیقه ی دیگر دوباره تلاش کنید")
                ]);
            }
        } else if (!$user && $practitioner) {
            $prevCode = $practitioner->verificationCodes()->where('expires_at', '>', Carbon::now())->first();
            if(!$prevCode) {
                $code = $this->makeVerificationCode($practitioner);
                $api = new GhasedakApi(env('GHASEDAKAPI_KEY'));
                $api->SendSimple(
                    "0".$phoneNumber,  // receptor
                    "کد احراز هویت شما در وبسایت آرامش \n " . $code, // message
                    "2000235"    // choose a line number from your account
                    );
                return Inertia::render('auth/UserConfirm', [
                    'phoneNumber'=> $phoneNumber,
                    'nextRoute' => request()->input('nextRoute'),
                ]);
            } else {
                return Inertia::render('auth/UserConfirm', [
                    'phoneNumber'=> $phoneNumber,
                    'nextRoute' => request()->input('nextRoute'),
                    'errors'=> array(0 => "کد فعالسازی قبلا برای شما ارسال شده است لطفا دو دقیقه ی دیگر دوباره تلاش کنید")
                ]);
            }
        }
    }

    protected function index()
    {
        return Inertia::render('auth/RegisterScreen');
    }

    protected function formOne()
    {
        return Inertia::render('auth/RegisterScreenForm1', [
            'cities' => City::all()
        ]);
    }

    protected function formTwo()
    {
        return Inertia::render('auth/RegisterScreenForm2');
    }

    protected function dobTable()
    {
        return Inertia::render('auth/DobTable');
    }

    protected function testTaker()
    {
        return Inertia::render('auth/TestTaker');
    }

    public function makeVerificationCode(Model $model) {
        $code = $this->codeGenerator($model);
        $model->verificationCodes()->create([
            'code' => $code,
            'expires_at' => Carbon::now()->addMinute(1)
        ]);
        return $code;
    }

    public function codeGenerator(Model $model) {
        $randomNumber = rand(100000, 999999);
        $codeExists = $model->verificationCodes()->where('code', $randomNumber)->where('expires_at', '>', Carbon::now())->get();
        if(sizeof($codeExists) > 0) {
            $this->codeGenerator($model);
        } else {
            return $randomNumber;
        }
    }
}
