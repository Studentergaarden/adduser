#!/usr/bin/env lem
-- -*- coding: utf-8 -*-


-- the package path is relative to where the script is called from.
-- this add the folder containing the script to the path! always!
function script_path()
   local str = debug.getinfo(2, "S").source:sub(2)
   return str:match("(.*/)")
end
if (script_path() ~= nil) then
	package.path =  package.path .. ';' .. script_path() .. '?.lua'
end

local inspect = require 'inspect'

local io = require 'lem.io'
local queue = require 'lem.io.queue'
local os = require 'os'

local sock_file = '/var/lock/sas.sock'
-- check if sock file exist
local socket = io.unix.listen(sock_file)
-- socket is nil, if the file exists
if socket == nil then
	os.remove(sock_file)
	socket = assert(io.unix.listen(sock_file))
end
local clients = {}

-- give apache permission to use the unix socket
local rtn = assert(os.execute('/bin/chown www-data:root ' .. sock_file),
                   'chown on sock file failed')

-- capture the return value of cmd
function os.capture(cmd, raw)
    local f = assert(io.popen(cmd, 'r'))
    local s = assert(f:read('*a'))
    f:close()
    if raw then return s end
    s = string.gsub(s, '^%s+', '')
    s = string.gsub(s, '%s+$', '')
    s = string.gsub(s, '[\n\r]+', ' ')
    return s
end

function parse_qs(str)
    -- save the decoded keys and values into a table
    local t = {}
    -- remove first and last character {,} respectively
    str = str:sub(2,-2)
    -- match everything from ',"key" : "value",' excluding '",'.
    for k, v in str:gmatch('([^,"]+)":"([^",]*)') do

        -- if the key contains [], then we recieved something that needs to be
        -- appended to an array
        k, replaced = k:gsub("%[%]","")
        if replaced > 0 or k == "id" then
            if t[k] == nil then t[k] = {} end
            table.insert(t[k],v)
        else
            t[k] = v
        end
    end
    return t
end

function mysplit(inputstr, sep)
    if sep == nil then
        sep = "%s"
    end
    local t={} ; i=1
    for str in string.gmatch(inputstr, "([^"..sep.."]+)") do
        t[i] = str
        i = i + 1
    end
    return t
end


function useradd(t)
    -- OS related stuff

    -- create user
    cmd = string.format("useradd -m -c '%s' -p '%s' %s",t["name"], t["password"], t["username"])
    -- print(cmd)
    local rtn = os.capture(cmd, true)

    -- get UID, GID, homedir from /etc/passwd file
    cmd = string.format("sed -n '/^%s:/p' /etc/passwd", t["username"])
    local rtn = os.capture(cmd)
    -- print("sed : ", rtn)
    local info = mysplit(rtn,':')
    t["uid"], t["gid"], t["userdir"] = info[3], info[4], info[6]

    --print(inspect(info))

    -- create maildir
    local maildir="/var/mail/maildirs/studentergaarden.dk/" .. t["username"]
    os.execute("mkdir " .. maildir)
    os.execute(string.format("chown %s:%s %s",t["username"], t["username"], maildir))

    -- mail forward
    assert(os.execute(string.format("echo %s > %s/.forward", t["email"], t["userdir"])))
    assert(os.execute(string.format("chown %s:www-data  %s/.forward", t["username"], t["userdir"])))
    assert(os.execute(string.format("chmod 660  %s/.forward", t["userdir"])))

    return t
end

local function log_user(t)
  local file = io.open("/var/log/adduser.log", "a")
  local time = os.date("*t")
  file:write(("  %04d/%02d/%02d %02d:%02d:%02d"):format(time.year, time.month, time.day, time.hour, time.min, time.sec))
  file:write('added user: ' .. t["username"] .. "\n")
  file:close()
end

local function create_msg(t,template)
  -- create return string in url format:
  -- key=val&key2=val2
  template = template or '%s=%s&'
  local tt = {}
  for k,v in pairs(t) do
    tt[#tt+1] = template:format(k,tostring(v))
  end
  local ret = table.concat(tt)
  -- remove last &
  return ret:sub(1, -2)
end

socket:autospawn(function(client)
	local self = queue.wrap(client)
	clients[self] = true

	while true do
		local line = client:read('*l')
		if not line then break end

		local t = parse_qs(line)
		--print('inspect: ' .. inspect(t))

		t = useradd(t)
		log_user(t)
		-- There's problem with utf8 encoding and mysql. Do the sas stuff in php instead.

		for c, _ in pairs(clients) do
		  if c == self then
		    t['success'] = '1';
		    local ret = create_msg(t)
		    c:write(ret)
		  end
		end
	end

	clients[self] = nil
	client:close()
end)



-- Local Variables:
-- lua-indent-level: 2
-- indent-tabs-mode: t
-- End:
