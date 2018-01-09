drop table if exists db_change_log;

create table db_change_log (
  delta_set varchar(5) not null,
  change_number int(11) not null,  
  filename varchar(255) not null,
  run_date timestamp not null default current_timestamp,
  primary key (delta_set, change_number)
);
