<?php
namespace App\Repositories;

use Illuminate\Http\Request;
use Kodeine\Acl\Models\Eloquent\Role;
use Lang;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;

class HelperRepository
{
    public function getFilters($form, $fields, Request $request)
    {
        $filters = $this->getFiltersValues($form, $fields);
        $filters['sort_url'] = $filters;
        unset($filters['sort_url']['sort']);
        
        $paginate = empty($request->session()->get('paginate')) ? 10 : $request->session()->get('paginate');
        $filters['paginate'] = ( empty($form['paginate']) || !is_numeric($form['paginate']) ) ?
                                        $paginate : $form['paginate'];
        $request->session()->put('paginate', $filters['paginate']);
        
        if (empty($form['sort'])) {
            $filters['sort'] = $fields[0];
            $filters['order'] = 'asc';
        } else {
            $sort = explode("-", $form['sort']);
            $filters['order'] = array_pop($sort);
            $filters['sort'] = implode("-", $sort);
            if (! in_array($filters['sort'], $fields)) {
                $filters['sort'] = $fields[0];
            }
        }
        
        $filters['pagination'] = $filters;
        $filters['pagination']['sort'] = str_replace("_", "-", $filters['sort']) . "-" . $filters['order'];
        unset($filters['pagination']['paginate']);
        unset($filters['pagination']['sort_url']);
        $filters = $this->getFiltersSortUrl($filters, $request);
        $filters['sort'] = str_replace("-", "_", $filters['sort']);

        return $filters;
    }

    private function getFiltersValues($form, $fields)
    {
        $filters = [];
        if (! empty($fields)) {
            foreach ($fields as $value) {
                $filters[$value] = empty($form[str_replace("-", "_", $value)]) ? "" :
                                    $form[str_replace("-", "_", $value)];
            }
        }
        return $filters;
    }

    private function getFiltersSortUrl($filters, Request $request)
    {
        $sortUrl = http_build_query($filters['sort_url']);
        foreach (array_keys($filters['sort_url']) as $key) {
            $url = $request->path() . '?' . $sortUrl . "&sort=" . $key;
            if ($filters['sort'] == $key && $filters['order'] == 'asc') {
                $filters['sort_url'][$key] = $url . "-desc";
                $filters['sort_icon'][$key] = "fa-sort-asc";
            } else {
                $filters['sort_url'][$key] = $url . "-asc";
                $filters['sort_icon'][$key] = "fa-sort";
                if ($filters['sort'] == $key) {
                    $filters['sort_icon'][$key] = "fa-sort-desc";
                }
            }
        }
        return $filters;
    }

    public function getAvailableRoles()
    {
        $role = Role::lists('name', 'id');
        $role = $role->transform(function ($item) {
            return Lang::get('general.' . $item);
        });
        return $role;
    }

    public function getAvailableLanguages()
    {
        $languages = [];
        $directories = File::directories(base_path() . DIRECTORY_SEPARATOR .
                            'resources' . DIRECTORY_SEPARATOR . 'lang');
        
        foreach ($directories as $directory) {
            $lang = explode(DIRECTORY_SEPARATOR, $directory);
            $lang = end($lang);
            $languages[$lang] = Lang::get('general.' . $lang);
        }
        return $languages;
    }

    public function getUserLanguage()
    {
        if (!empty(Auth::user()['language'])) {
            return Auth::user()['language'];
        } else {
            $availableLangs = $this->getAvailableLanguages();
            $browserLanguages = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            
            foreach ($browserLanguages as $lang) {
                if (in_array($lang, $availableLangs)) {
                    return $lang;
                }
            }
        }
        return 'en';
    }

    public function validateRecord($record)
    {
        if (empty($record) || $record->company_id != Auth::user()['company_id']) {
            Redirect::to('/')->with('danger', Lang::get('general.accessdenied'))->send();
        }
        
        if (method_exists($record, 'checkCompanyRelationships') && !empty($record->checkCompanyRelationships())) {
            foreach ($record->checkCompanyRelationships() as $field => $entity) {
                if ($record->$field) {
                    $namespacedEntity = '\\App\\Entities\\' . $entity;
                    $count = $namespacedEntity::where('id', $record->$field)
                                    ->where('company_id', Auth::user()['company_id'])
                                    ->count();
                    if ($count == 0) {
                        Redirect::to('/')->with('danger', Lang::get('general.accessdenied'))->send();
                    }
                }
            }
        }
    }
    
    public static function money($value, $mask = 'en', $decimal = 2)
    {
        $value = preg_replace("/[^0-9]/", "", $value);
        if (strlen($value) < 3) {
            $value = str_pad($value, 3, "0", STR_PAD_LEFT);
        }
        $value = substr($value, 0, ($decimal * -1)) . "." . substr($value, ($decimal * -1));
        if ($mask == 'pt-br') {
            return number_format($value, $decimal, ',', '.');
        } elseif ($mask == 'en') {
            return number_format($value, $decimal, '.', '');
        } elseif ($mask == 'url') {
            return preg_replace("/[^0-9]/", "", $value);
        }
        return preg_replace("/[^0-9]/", "", $value);
    }

    public static function date($value, $mask = 'en')
    {
    
        $hour = "";
        if (strlen($value) > 10) {
            $datetime = explode(" ", $value);
            $value = $datetime[0];
            $hour = " ".$datetime[1];
        }
    
        $originalMask = self::dataGetMask($value);
    
        if ($originalMask == $mask) {
            return $value.$hour;
        } elseif ($mask == 'pt-br') {
            if ($originalMask == 'en') {
                return implode("/", array_reverse(explode("-", $value))).$hour;
            }
        } elseif ($mask == 'en') {
            if ($originalMask == 'pt-br') {
                return implode("-", array_reverse(explode("/", $value))).$hour;
            }
        }
    
        return '';
    }
    
    public static function dataGetMask($value)
    {
        if (preg_match("/[0-9]{2}\/[0-9]{2}\/[0-9]{4}/", $value)) {
            return 'pt-br';
        } elseif (preg_match("/[0-9]{4}\-[0-9]{2}\-[0-9]{2}/", $value)) {
            return 'en';
        }
        return '';
    }
}
