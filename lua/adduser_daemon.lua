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
local iolua = require 'io'
local queue = require 'lem.io.queue'
local os = require 'os'

-- mySQL
local env = require 'luasql.mysql'.mysql()
local db  = require 'db_credentials'

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

function insertSAS(t)
    -- mySQL related stuff


    local con = env:connect(db["table"],db["user"],db["password"],db["host"])
    local mtime = os.date("%Y-%m-%d %X")

    -- insert into name table. name_id is auto-incremented
    local str = string.format("INSERT INTO name SET name='%s',room='%s',title='%s',status='%s',public='1',mtime='%s';",
                              t["name"], t["room"], t["study"], t["status"], mtime)
    print(str)
    local res = assert(con:execute(str))

    -- get name_id
    str = string.format("SELECT name_id from name WHERE name='%s' AND room='%s';",t["name"], t["room"]);
    print(str)
    res = assert(con:execute(str))
    t["nid"] = res:fetch()

    if (t["phone"] ~= nil or t["phone"] ~= '') then
        str = string.format("INSERT INTO info (info ,name_id, type, public, mtime) VALUES (%s, %s, 'mobil', '0', now())",
                            t["phone"], t["nid"])
        print(str)
        res = assert(con:execute(str))
    end

    -- insert into user table
    str = string.format("INSERT INTO user SET user_id='%s', user='%s', name_id='%s', print='10', public='1', mtime='%s';",
                        t["uid"], t["username"], t["nid"], mtime)
    print(str)
    res = assert(con:execute(str))

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

    print(inspect(info))

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


socket:autospawn(function(client)
	local self = queue.wrap(client)
	clients[self] = true

	while true do
		local line = client:read('*l')
		if not line then break end

		local t = parse_qs(line)
		print('inspect: ' .. inspect(t))

		t = useradd(t)
		t = insertSAS(t)

		for c, _ in pairs(clients) do
			if c == self then
				c:write(string.format('success:1\n'))
			end
		end
	end

	clients[self] = nil
	client:close()
end)



-- Local Variables:
-- lua-indent-level: 4
-- indent-tabs-mode: t
-- End:
