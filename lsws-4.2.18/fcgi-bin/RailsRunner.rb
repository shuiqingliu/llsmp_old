#!/usr/bin/ruby

$0="RAILS: #{ENV['APP_NAME'] || ENV['RAILS_ROOT']} (#{ENV['RAILS_ENV']})"
if GC.respond_to?(:copy_on_write_friendly=)
    GC.copy_on_write_friendly = true
end

Dir.chdir( ENV['RAILS_ROOT'] )
require 'rails/version'
require './config/boot'

if defined? Rails::VERSION
    rails_ver = Rails::VERSION::STRING
elsif defined? RAILS_GEM_VERSION
    rails_ver = RAILS_GEM_VERSION
elsif ENV.include?('RAILS_GEM_VERSION')
    rails_ver = ENV['RAILS_GEM_VERSION']
else
    environmentrb = File.read('config/environment.rb')
    rails_ver = $1 if environmentrb =~ /^[^#]*RAILS_GEM_VERSION\s*=\s*["']([!~<>=]*\s*[\d.]+)["']/
end
app_root=ENV['RAILS_ROOT']
if rails_ver != nil and rails_ver >= '2.3.0'
    #use rack
    require 'active_support'
    require 'action_controller'
    require 'fileutils'

    require 'rack/content_length'
    require 'lsapi'

    module Rack
      module Handler
        class LiteSpeed
          def self.run(app, options=nil)
            while LSAPI.accept != nil
                serve app
            end
          end
          def self.serve(app)
            app = Rack::ContentLength.new(app)

            env = ENV.to_hash
            env.delete "HTTP_CONTENT_LENGTH"
            env["SCRIPT_NAME"] = "" if env["SCRIPT_NAME"] == "/"

            rack_input = StringIO.new($stdin.read.to_s)

            env.update(
              "rack.version" => [1,0],
              "rack.input" => rack_input,
              "rack.errors" => $stderr,
              "rack.multithread" => false,
              "rack.multiprocess" => true,
              "rack.run_once" => false,
              "rack.url_scheme" => ["yes", "on", "1"].include?(ENV["HTTPS"]) ? "https" : "http"
            )

            env["QUERY_STRING"] ||= ""
            env["HTTP_VERSION"] ||= env["SERVER_PROTOCOL"]
            env["REQUEST_PATH"] ||= "/"
            status, headers, body = app.call(env)
            begin
              send_headers status, headers
              send_body body
            ensure
              body.close if body.respond_to? :close
            end
          end
          def self.send_headers(status, headers)
            print "Status: #{status}\r\n"
            headers.each { |k, vs|
              vs.split("\n").each { |v|
                print "#{k}: #{v}\r\n"
              }
            }
            print "\r\n"
            STDOUT.flush
          end
          def self.send_body(body)
            body.each { |part|
              print part
              STDOUT.flush
            }
          end
        end
      end
    end
    
    options = {
        :environment => (ENV['RAILS_ENV'] || "development").dup,
        :config => "#{app_root}/config.ru",
        :detach => false,
        :debugger => false
    }
    
    server = Rack::Handler::LiteSpeed
    
    if File.exist?(options[:config])
        config = options[:config]
    if config =~ /\.ru$/
        cfgfile = File.read(config)
        if cfgfile[/^#\\(.*)/]
            opts.parse!($1.split(/\s+/))
        end
        inner_app = eval("Rack::Builder.new {( " + cfgfile + "\n )}.to_app", nil, config)
    else
        require config
        inner_app = Object.const_get(File.basename(config, '.rb').capitalize)
    end
    else
        require './config/environment'
        inner_app = ActionController::Dispatcher.new
    end
    
    app = Rack::Builder.new {
        use Rails::Rack::Debugger if options[:debugger]
        run inner_app
    }.to_app

    ActiveRecord::Base.clear_all_connections! if defined?(ActiveRecord::Base)

    begin
        server.run(app, options.merge(:AccessLog => []))
    ensure
        puts 'Exiting'
    end
 
else
  
    require './config/environment'
    
    require 'initializer'
    require 'dispatcher'
    
    #require 'breakpoint' if defined?(BREAKPOINT_SERVER_PORT)

    #if RAILS_ENV=='production'
    #   require_dependency 'application'
    #   Dir.foreach( "app/models" ) {|f| silence_warnings{require_dependency f} if f =~ /\.rb$/}
    #   Dir.foreach( "app/controllers" ) {|f|  silence_warnings{require_dependency f} if f =~ /\.rb$/}
    #end

    require 'lsapi'

    #Close all DB connections established during initialization
    if defined?(ActiveRecord::Base)
    if defined?(ActiveRecord::Base.clear_active_connections!)
        ActiveRecord::Base.clear_active_connections!
    else
        ActiveRecord::Base.connection.disconnect!
        @reconnect = true
        ActiveRecord::Base.establish_connection
    end
    end

    while LSAPI.accept != nil
        if defined?(ActiveRecord::Base) and @reconnect
            ActiveRecord::Base.connection.reconnect!
            @reconnect = false
        end

        Dispatcher.dispatch
    end
end
