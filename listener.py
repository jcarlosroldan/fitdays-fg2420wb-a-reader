from datetime import datetime, timezone
from json import dumps, loads
from math import inf, sqrt
from os import chdir
from os.path import abspath, dirname, exists
from time import time
from dbus import Array, Interface, SystemBus
from dbus.mainloop.glib import DBusGMainLoop
from gi.repository.GLib import MainLoop
from setproctitle import setproctitle

UUID = '0000ffb%d-0000-1000-8000-00805f9b34fb'
ADAPTER = 'org.bluez.Adapter1'
DEVICE = 'org.bluez.Device1'
CHARACTERISTIC = 'org.bluez.GattCharacteristic1'
PROPERTIES = 'org.freedesktop.DBus.Properties'
MANAGER = 'org.freedesktop.DBus.ObjectManager'

DBusGMainLoop(set_as_default=True)
bus = SystemBus()
session = {}

def main():
	chdir(dirname(abspath(__file__)))
	setproctitle('bt_scale_listen')
	bus.add_signal_receiver(lambda path, interfaces: DEVICE in interfaces and connect_if_scale(path), dbus_interface=MANAGER, signal_name='InterfacesAdded')
	bus.add_signal_receiver(dispatch_change, dbus_interface=PROPERTIES, signal_name='PropertiesChanged', path_keyword='path')
	scan()
	MainLoop().run()

def connect_if_scale(path):
	if not session and bluez(path, PROPERTIES).GetAll(DEVICE).get('Name') == 'MY_SCALE':
		session.update(device=path, characteristics={}, partial={}, index=0, outbox=[])
		bluez('/org/bluez/hci0', ADAPTER).StopDiscovery()
		bluez(path, DEVICE).Connect(reply_handler=subscribe, error_handler=disconnect)

def dispatch_change(name, values, invalidated, path):
	if name == DEVICE and path == session.get('device'):
		if values.get('ServicesResolved'):
			subscribe()
		if values.get('Connected') == False:
			end_session()
	elif name == DEVICE and ('RSSI' in values or 'ManufacturerData' in values):
		connect_if_scale(path)
	elif name == CHARACTERISTIC and 'Value' in values and session.get('characteristics'):
		read_frame(path, bytes(values['Value']))

def scan():
	adapter = bluez('/org/bluez/hci0', ADAPTER)
	adapter.SetDiscoveryFilter({'Transport': 'le', 'DuplicateData': True, 'UUIDs': [UUID % 0]})
	adapter.StartDiscovery()

def subscribe():
	if not session or session['characteristics'] or not bluez(session['device'], PROPERTIES).Get(DEVICE, 'ServicesResolved'):
		return
	objects = bluez('/', MANAGER).GetManagedObjects().items()
	session['characteristics'] = {str(interfaces[CHARACTERISTIC]['UUID']): path for path, interfaces in objects if path.startswith(session['device']) and CHARACTERISTIC in interfaces}
	if {UUID % 1, UUID % 2, UUID % 3} - session['characteristics'].keys():
		disconnect('not the scale')
		return
	characteristic(3).StartNotify(reply_handler=lambda: characteristic(2).StartNotify(reply_handler=lambda: None, error_handler=disconnect), error_handler=disconnect)

def disconnect(reason):
	print(datetime.now().strftime('%Y-%m-%d %H:%M:%S'), 'session failed:', reason, flush=True)
	if session:
		bluez(session['device'], DEVICE).Disconnect(reply_handler=lambda: None, error_handler=lambda error: None)
		end_session()

def end_session():
	if session:
		session.clear()
		scan()

def read_frame(path, frame):
	index, length = frame[0], int.from_bytes(frame[1:3], 'big')
	packet = session['partial'].pop((path, index), b'') + frame[4:-1]
	if len(packet) < length:
		session['partial'][(path, index)] = packet
		return
	if packet[0] == 0xa2:
		return
	if packet[0] in (0xa1, 0xaa):
		send(bytes([0xb0, index, 0]))
		offset = int(datetime.now().astimezone().utcoffset().total_seconds() // 60)
		for user in b'\x01\xac\x17\x70\x98\x13\x88\x13\x88\x3f\x00\x00\x00\x00\x00\x00\x07welland', b'\x00\xb8\x17\x70\xa1\x13\x88\x13\x88\x2f\x00\x00\x00\x00\x01\x01\x08Invitado':
			send(b'\xc0' + int(time()).to_bytes(4, 'big') + offset.to_bytes(2, 'big') + user)
	elif packet[0] in (0xa3, 0xa5, 0xa7):
		send(bytes([0xb0, index, 0]))
		store_weighing(int.from_bytes(packet[1:5], 'big'), (int.from_bytes(packet[5:9], 'big') & 0x3ffff) / 1000, [int.from_bytes(packet[11 + 2 * i:13 + 2 * i], 'big') / 10 for i in range(packet[10])])

def send(payload):
	session['outbox'].append(bytes([session['index']]) + len(payload).to_bytes(2, 'big') + b'\x00' + payload + bytes([sum(payload) & 0x1f]))
	session['index'] = (session['index'] + 1) % 256
	if len(session['outbox']) == 1:
		write_next()

def write_next():
	characteristic(1).WriteValue(Array(session['outbox'][0], signature='y'), {'type': 'request'}, reply_handler=write_after_reply, error_handler=disconnect)

def write_after_reply():
	session['outbox'].pop(0)
	if session['outbox']:
		write_next()

def store_weighing(measured, weight, impedances):
	if not all(impedances):
		return
	reading = {'time': datetime.fromtimestamp(measured, timezone.utc).isoformat(), 'weight': weight, 'impedances': impedances}
	latest = {previous['person']['name']: previous for previous in map(loads, open('readings.jsonl'))} if exists('readings.jsonl') else {}
	deviations = {person['name']: deviation(latest[person['name']], reading) if person['name'] in latest else inf for person in loads(open('people.json').read())}
	name = min(deviations, key=deviations.get)
	reading['person'] = {'name': name, 'confidence': round(1 / (1 + deviations[name] ** 2), 2)}
	with open('readings.jsonl', 'a') as file:
		file.write(dumps(reading) + '\n')

def deviation(previous, reading):
	drift = sqrt(max(0, datetime.fromisoformat(reading['time']).timestamp() - datetime.fromisoformat(previous['time']).timestamp()) / 604800)
	return max(abs(reading['weight'] - previous['weight']) / (2.5 + 1.2 * drift), sum(abs(now - before) / before for now, before in zip(reading['impedances'], previous['impedances'])) / len(reading['impedances']) / (0.12 + 0.03 * drift))

def bluez(path, name):
	return Interface(bus.get_object('org.bluez', path), name)

def characteristic(number):
	return bluez(session['characteristics'][UUID % number], CHARACTERISTIC)

if __name__ == '__main__':
	main()
