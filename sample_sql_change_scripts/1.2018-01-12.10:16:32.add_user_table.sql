drop table if exists test_users_table;

create table test_users_table (
    id int unsigned not null primary key auto_increment,
    first_name varchar(80) not null default ''
);