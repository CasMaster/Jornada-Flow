<?php
namespace App\Http\Controllers;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
class AdminController extends Controller
{
    public function team(Request $request): RedirectResponse
    {
        $data=$request->validate(['name'=>['required','string','max:100','unique:teams']]); Team::create(['name'=>$data['name'],'active'=>true]); return back()->with('success','Equipe criada.');
    }
    public function toggleTeam(Team $team): RedirectResponse { $team->update(['active'=>!$team->active]); return back()->with('success','Equipe atualizada.'); }
}
