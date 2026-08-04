<?php
namespace App\Http\Controllers;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
class AdminController extends Controller
{
    public function team(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:100','unique:teams']]); Team::create(['name'=>$data['name'],'active'=>true]); return back()->with('success','Equipe criada.');
    }
    public function toggleTeam(Team $team): RedirectResponse { $team->update(['active'=>!$team->active]); return back()->with('success','Equipe atualizada.'); }
    public function user(Request $request, ?User $user=null): RedirectResponse
    {
        $rules=['name'=>['required','string','max:150'],'email'=>['required','email',Rule::unique('users')->ignore($user?->id)],'role'=>['required','in:employee,manager,super_admin'],'team'=>['nullable','string'],'manager_teams'=>['array'],'manager_teams.*'=>['integer','exists:teams,id'],'password'=>[$user?'nullable':'required',Password::min(8)]];
        $data=$request->validate($rules); $target=$user ?: new User; $target->fill(['name'=>$data['name'],'email'=>strtolower($data['email']),'role'=>$data['role'],'team'=>$data['team']??'','active'=>$target->exists?$target->active:true]); if(!empty($data['password']))$target->password=$data['password']; $target->save();
        $target->managedTeams()->sync($data['role']==='manager'?($data['manager_teams']??[]):[]); return back()->with('success','Usuário salvo.');
    }
    public function toggleUser(Request $request, User $user): RedirectResponse { abort_if($request->user()->is($user),422,'Você não pode desativar a própria conta.'); $user->update(['active'=>!$user->active]); return back()->with('success','Usuário atualizado.'); }
}
