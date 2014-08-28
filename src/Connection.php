<?php
namespace Behance\Cassandra;
use Behance\Cassandra\Cluster\Node;
use Behance\Cassandra\Enum;
use Behance\Cassandra\Exception\ConnectionException;
use Behance\Cassandra\Protocol\Frame;
use Behance\Cassandra\Protocol\Request;
use Behance\Cassandra\Protocol\Response;

class Connection {

	/**
	 * @var Cluster
	 */
	private $cluster;

	/**
	 * @var Node
	 */
	private $node;

	/**
	 * @var resource
	 */
	private $connection;

	/**
	 * @param Cluster $cluster
	 */
	public function __construct(Cluster $cluster) {
		$this->cluster = $cluster;
	}

	public function connect() {
		try {
			$this->node = $this->cluster->getRandomNode();
			$this->connection = $this->node->getConnection();
		} catch (ConnectionException $e) {
			$this->connect();
		}
	}

	/**
	 * @return bool
	 */
	public function disconnect() {
		return socket_shutdown($this->connection);
	}

	/**
	 * @return bool
	 */
	public function isConnected() {
		return $this->connection !== null;
	}

	/**
	 * @param Request $request
	 * @return \Cassandra\Protocol\Response
	 */
	public function sendRequest(Request $request) {
		$frame = new Frame(Enum\VersionEnum::REQUEST, $request->getType(), $request);
		socket_write($this->connection, $frame);
		return $this->getResponse();
	}

	/**
	 * @param $length
	 * @throws Exception\ConnectionException
	 * @return string
	 */
	private function fetchData($length) {
		$data = socket_read($this->connection, $length);
		while (strlen($data) < $length) {
			$data .= socket_read($this->connection, $length);
		}
		if (socket_last_error($this->connection) == 110) {
			throw new ConnectionException('Connection timed out');
		}

		return $data;
	}

	private function getResponse() {
		$data = $this->fetchData(8);
		$data = unpack('Cversion/Cflags/cstream/Copcode/Nlength', $data);
		if ($data['length']) {
			$body = $this->fetchData($data['length']);
		} else {
			$body = '';
		}

		return new Response($data['opcode'], $body);
	}

	/**
	 * @return \Cassandra\Cluster\Node
	 */
	public function getNode() {
		return $this->node;
	}
}
