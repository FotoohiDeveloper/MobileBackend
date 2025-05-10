<?php

   namespace App\Models;

   use Illuminate\Database\Eloquent\Model;

   class Ticket extends Model
   {
       protected $fillable = ['user_id', 'department_id', 'assigned_to', 'title', 'description', 'priority', 'status', 'attachment'];

       public function user()
       {
           return $this->belongsTo(User::class);
       }

       public function department()
       {
           return $this->belongsTo(Department::class);
       }

       public function assignedUser()
       {
           return $this->belongsTo(User::class, 'assigned_to');
       }

       public function messages()
       {
           return $this->hasMany(Message::class);
       }
   }