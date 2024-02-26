# Reading PZEM-004t power sensor (new version v3.0) through Modbus-RTU protocol over TTL UART
# Run on python3

# To install dependencies:
# pip install modbus-tk
# pip install pyserial

import serial
import modbus_tk.defines as cst
from modbus_tk import modbus_rtu
import os
import time
import datetime
import traceback
import logging
import string
import sys
import argparse
#import thread
import re
import signal
from optparse import OptionParser
from os.path import join
import subprocess
#import urllib2
import threading
import globals
import json

try:
	from jeedom.jeedom import *
except ImportError as e:
	print("Error: importing module from jeedom folder " + str(e))
	sys.exit(1)

# PZEM
PZEM=[]
    
def read_modbus():
	#logging.debug("--> Read PZEM data")
	for pzem in PZEM:
		try:
			#logging.debug("--> Read PZEM data @:" + str(pzem))
			data = Modbus.execute(pzem, cst.READ_INPUT_REGISTERS, 0, 10)
			#logging.debug('Voltage [V]: ' + str(data[0] / 10.0)) 
			#logging.debug('Current [A]: ' + str(data[1] + (data[2] << 16) / 1000.0))
			#logging.debug('Power [W]: ' + str((data[3] + (data[4] << 16)) / 10.0)) 
			#logging.debug('Energy [Wh]: ' + str(data[5] + (data[6] << 16))) 
			#logging.debug('Frequency [Hz]: ' + str(data[7] / 10.0))
			#logging.debug('Power factor []: ' + str(data[8] / 100.0))
			#alarm = data[9] # 0 = no alarm

			#Construction JSON
			action = {}
			action['adresse'] = str(pzem)
			action['voltage'] = str(data[0] / 10.0)
			action['current'] = str((data[1] + (data[2] << 16)) / 1000.0)
			action['power'] = str((data[3] + (data[4] << 16)) / 10.0)
			action['energy'] = str(data[5] + (data[6] << 16))
			action['frequency'] = str(data[7] / 10.0)
			action['powerfactor'] = str(data[8] / 100.0)
			
			try:
				globals.JEEDOM_COM.add_changes('device::'+action['adresse'],action)
			except Exception as e:
				logging.debug("Send error on socket") 
				pass

		except Exception as e:
			logging.debug("read error on modbus") 
			pass

		time.sleep(_cycle)

def read_socket():
	try:
		global JEEDOM_SOCKET_MESSAGE
		if not JEEDOM_SOCKET_MESSAGE.empty():
			logging.debug("--> Message received in socket JEEDOM_SOCKET_MESSAGE")
			message = JEEDOM_SOCKET_MESSAGE.get().decode('utf-8')
			message =json.loads(message)
			if message['apikey'] != _apikey:
				logging.error("Invalid apikey from socket : " + str(message))
				return

			if message['apikey'] == _apikey:

				if message['cmd'] == 'add':
					if message['device'] !='':
						device = int(message['device'])
						if device not in PZEM:
							logging.debug("Add the PZEM with @:" + str(device)) 
							PZEM.append(device)

				if message['cmd'] == 'set':
					if message['device'] !='':
						if message['newaddress'] !='':
							device = int(message['device'])
							newaddress = int(message['newaddress'])
							if device in PZEM:
								if newaddress not in PZEM:
									logging.info("change the PZEM with @:" + str(device) + " with the @:" + str(newaddress)) 
									Modbus.execute(device, cst.WRITE_SINGLE_REGISTER, 2, output_value=newaddress)
									PZEM.append(newaddress)
									PZEM.remove(device)
									time.sleep(2)
								else:
									logging.info("The PZEM with @:" + str(device) + " can not be change. The new adress is already referenced") 	
							else:
								logging.info("The PZEM with @:" + str(device) + " can not be change. The adress is not referenced") 

				if message['cmd'] == 'del':
					if message['device'] !='' :
						device = int(message['device'])
						if device in PZEM:
							logging.debug("remove the PZEM with @:" + str(device)) 
							PZEM.remove(device)

	except Exception:
		logging.error('Error on read socket')

def listen():
    logging.debug("Start listening...")
    # Start Serial
    jeedom_serial = serial.Serial(port=_port,baudrate=_vitesse,bytesize=8,parity='N',stopbits=1,xonxoff=0)
    # Start Modbus
    Modbus = modbus_rtu.RtuMaster(jeedom_serial)
    Modbus.set_timeout(2.0)
    Modbus.set_verbose(False)
    # Start Socket
    jeedom_socket.open()
    logging.debug("Start deamon")
    try:
        while 1:
            time.sleep(_cycle)
            read_modbus()
            read_socket()
    except KeyboardInterrupt:
        shutdown()
# ----------------------------------------------------------------------------
def handler(signum=None, frame=None):
	logging.debug("Signal %i caught, exiting..." % int(signum))
	shutdown()
	
# ----------------------------------------------------------------------------
def shutdown():
	logging.debug("Shutdown")
	logging.debug("Removing PID file " + str(_pidfile))
	try:
		os.remove(_pidfile)
	except:
		pass
	try:
		jeedom_socket.close()
	except:
		pass
	try:
		Modbus.close()
		if jeedom_serial.is_open:
			jeedom_serial.close()
	except:
		pass
	try:
		byte.close()
	except:
		pass
	logging.debug("Exit 0")
	sys.stdout.flush()
	os._exit(0)

# ----------------------------------------------------------------------------
_log_level = "error"
_socket_port = 55559
_socket_host = 'localhost'
_port = '/dev/ttyUSB0'
_vitesse=9600
_pidfile = '/tmp/jeedom/pzem/pzem.pid'
_apikey = ''
_callback = ''
_cycle = 0.3
parser = argparse.ArgumentParser(description='pzem Daemon for Jeedom plugin')
parser.add_argument("--port", help="Port", type=str)
parser.add_argument("--vitesse", help="Vitesse du modem", type=str)
parser.add_argument("--socketport", help="Socketport for server", type=str)
parser.add_argument("--loglevel", help="Log Level for the daemon", type=str)
parser.add_argument("--callback", help="Callback", type=str)
parser.add_argument("--apikey", help="Apikey", type=str)
parser.add_argument("--cycle", help="Cycle to send event", type=str)
parser.add_argument("--pid", help="Pid file", type=str)
args = parser.parse_args()

if args.port:
	_port = args.port
if args.vitesse:
	_vitesse = args.vitesse
if args.socketport:
    _socket_port = int(args.socketport)
if args.loglevel:
    _log_level = args.loglevel
if args.callback:
    _callback = args.callback
if args.apikey:
    _apikey = args.apikey
if args.pid:
    _pidfile = args.pid
if args.cycle:
    _cycle = float(args.cycle)

_socket_port = int(_socket_port)

jeedom_utils.set_log_level(_log_level)

logging.info('Start i2cExt daemon')
logging.info('Log level : '+str(_log_level))
logging.info('Socket port : '+str(_socket_port))
logging.info('Socket host : '+str(_socket_host))
logging.info('PID file : '+str(_pidfile))
logging.info('Port : '+str(_port))
logging.info('Vitesse : '+str(_vitesse))
logging.info('Apikey : '+str(_apikey))
logging.info('Callback : '+str(_callback))
logging.info('Cycle : '+str(_cycle))

signal.signal(signal.SIGINT, handler)
signal.signal(signal.SIGTERM, handler)	

try:
    jeedom_utils.write_pid(str(_pidfile))
    globals.JEEDOM_COM = jeedom_com(apikey = _apikey,url = _callback,cycle=_cycle)
    print('api ',_apikey)
    if not globals.JEEDOM_COM.test():
        logging.error('Network communication issues. Please fixe your Jeedom network configuration.')
        shutdown()
    jeedom_socket = jeedom_socket(port=_socket_port,address=_socket_host)
    # Start Serial
    	#jeedom_serial = serial.Serial(port=_port,baudrate=_vitesse,bytesize=8,parity='N',stopbits=1,xonxoff=0)
    # Start Modbus
     	#Modbus = modbus_rtu.RtuMaster(jeedom_serial)
     	#Modbus.set_timeout(0.3)
     	#Modbus.set_verbose(False)
    listen()
except Exception as e:
    logging.error('Fatal error : '+str(e))
    logging.debug(traceback.format_exc())
    shutdown()
