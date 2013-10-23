#require "bundler/capistrano"
require 'railsless-deploy'

set :deploy_via, :remote_cache
set :application, "weixuhong.com"
server "wxh", :web, :app, :db, primary: true
set :repository,  "git://github.com/gxbsst/weixuhong_blog.git"
set :user, "deployer"
set :deploy_to, "/home/#{user}/apps/#{application}"

set :scm, :git
set :use_sudo, false

set :branch, "master"

default_run_options[:pty] = true
ssh_options[:forward_agent] = true

namespace :deploy do

	task :setup_config, roles: :app do
		run "mkdir -p #{shared_path}/config/env"
		run "mkdir -p #{shared_path}/uploads"
		# run "mkdir -p #{shared_path}/site"
		
		# put File.read("public/wp-config.php"), "#{shared_path}/config/wp-config.php"
		# put File.read("config/wordpress.php"), "#{shared_path}/config/wordpress.php"
		put File.read("config/env/development.php.example"), "#{shared_path}/config/env/development.php"
		put File.read("config/env/production.php.example"), "#{shared_path}/config/env/production.php"
		puts "Now edit the config files in #{shared_path}."
	end

	after "deploy:setup", "deploy:setup_config"

	task :symlink_config, roles: :app do
		# run "rm -rvf #{release_path}/public/site"
		# run "ln -nfs #{shared_path}/config/wp-config.php #{release_path}/public/wp-config.php"
		# run "ln -nfs #{shared_path}/config/wordpress.php #{release_path}/config/wordpress.php"
		# run "ln -nfs #{shared_path}/config/env/local.php #{release_path}/config/env/local.php"
		run "ln -nfs #{shared_path}/config/env/development.php #{release_path}/config/env/development.php"
		run "ln -nfs #{shared_path}/config/env/production.php #{release_path}/config/env/production.php"
		run "ln -nfs #{shared_path}/uploads #{release_path}/public/content/uploads"
		# run "ln -nfs #{shared_path}/site #{release_path}/public/"

  #   	run("chmod -R 777 #{current_path}/public/content/uploads")
end
after "deploy:finalize_update", "deploy:symlink_config"

end

