<?php
namespace Audeio\Spotify;

use Audeio\Spotify\Entity\Album;
use Audeio\Spotify\Entity\AlbumCollection;
use Audeio\Spotify\Entity\AlbumPagination;
use Audeio\Spotify\Entity\Artist;
use Audeio\Spotify\Entity\ArtistCollection;
use Audeio\Spotify\Entity\Pagination;
use Audeio\Spotify\Entity\Playlist;
use Audeio\Spotify\Entity\PlaylistCollection;
use Audeio\Spotify\Entity\PlaylistPagination;
use Audeio\Spotify\Entity\PlaylistTrackPagination;
use Audeio\Spotify\Entity\Track;
use Audeio\Spotify\Entity\TrackCollection;
use Audeio\Spotify\Entity\TrackPagination;
use Audeio\Spotify\Entity\User;
use Audeio\Spotify\Exception\AccessTokenExpiredException;
use Audeio\Spotify\Hydrator\AlbumAwareHydrator;
use Audeio\Spotify\Hydrator\AlbumCollectionHydrator;
use Audeio\Spotify\Hydrator\AlbumHydrator;
use Audeio\Spotify\Hydrator\ArtistCollectionAwareHydrator;
use Audeio\Spotify\Hydrator\ArtistCollectionHydrator;
use Audeio\Spotify\Hydrator\ArtistHydrator;
use Audeio\Spotify\Hydrator\ImageCollectionAwareHydrator;
use Audeio\Spotify\Hydrator\OwnerAwareHydrator;
use Audeio\Spotify\Hydrator\PaginatedAlbumCollectionHydrator;
use Audeio\Spotify\Hydrator\PaginatedPlaylistCollectionHydrator;
use Audeio\Spotify\Hydrator\PaginatedPlaylistTrackCollectionAwareHydrator;
use Audeio\Spotify\Hydrator\PaginatedPlaylistTrackCollectionHydrator;
use Audeio\Spotify\Hydrator\PaginatedTrackCollectionAwareHydrator;
use Audeio\Spotify\Hydrator\PaginatedTrackCollectionHydrator;
use Audeio\Spotify\Hydrator\PaginationHydrator;
use Audeio\Spotify\Hydrator\PlaylistCollectionHydrator;
use Audeio\Spotify\Hydrator\PlaylistHydrator;
use Audeio\Spotify\Hydrator\TrackCollectionHydrator;
use Audeio\Spotify\Hydrator\TrackHydrator;
use Audeio\Spotify\Hydrator\TracksAwareHydrator;
use Audeio\Spotify\Hydrator\UserHydrator;
use GuzzleHttp;
use Zend\Stdlib\Hydrator\Aggregate\AggregateHydrator;

/**
 * Class API
 * @package Audeio\Spotify
 */
class API
{

    private static $baseUrl = 'https://api.spotify.com';

    /**
     * @var GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * @var string
     */
    private $accessToken;

    /**
     *
     */
    public function __construct()
    {
        $this->guzzleClient = new GuzzleHttp\Client([
            'base_url' => static::$baseUrl,
            'defaults' => [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => sprintf('Bearer %s', $this->accessToken)
                ]
            ]
        ]);
    }

    /**
     * @param string $accessToken
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;

        $this->guzzleClient->setDefaultOption('headers/Authorization', sprintf('Bearer %s', $this->accessToken));
    }

    /**
     * @param string $id
     * @return Album
     */
    public function getAlbum($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/albums/%s', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new AlbumHydrator());
        $hydrators->add(new ArtistCollectionAwareHydrator());
        $hydrators->add(new ImageCollectionAwareHydrator());
        $hydrators->add(new PaginatedTrackCollectionAwareHydrator());

        return $hydrators->hydrate($response, new Album());
    }

    /**
     * @param array $ids
     * @return AlbumCollection
     */
    public function getAlbums(array $ids)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', '/v1/albums', array(
                'query' => array(
                    'ids' => implode(',', $ids)
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new AlbumCollectionHydrator());

        return $hydrators->hydrate($response, new AlbumCollection());
    }

    /**
     * @param string $id
     * @param int $limit
     * @param int $offset
     * @return TrackPagination
     */
    public function getAlbumTracks($id, $limit = 20, $offset = 0)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/albums/%s/tracks', $id), array(
                'query' => array(
                    'limit' => $limit,
                    'offset' => $offset
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new PaginationHydrator());
        $hydrators->add(new PaginatedTrackCollectionHydrator());

        return $hydrators->hydrate($response, new TrackPagination());
    }

    /**
     * @param string $id
     * @return Artist
     */
    public function getArtist($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/artists/%s', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new ArtistHydrator());
        $hydrators->add(new ImageCollectionAwareHydrator());

        return $hydrators->hydrate($response, new Artist());
    }

    /**
     * @param array $ids
     * @return ArtistCollection
     */
    public function getArtists(array $ids)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', '/v1/artists', array(
                'query' => array(
                    'ids' => implode(',', $ids)
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new ArtistCollectionHydrator());

        return $hydrators->hydrate($response, new ArtistCollection());
    }

    /**
     * @param string $id
     * @param string $country
     * @param array $albumTypes
     * @param int $limit
     * @param int $offset
     * @return AlbumPagination
     */
    public function getArtistAlbums($id, $country, array $albumTypes, $limit = 20, $offset = 0)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/artists/%s/albums', $id), array(
                'query' => array(
                    'album_type' => implode(',', $albumTypes),
                    'country' => $country,
                    'limit' => $limit,
                    'offset' => $offset
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new PaginationHydrator());
        $hydrators->add(new PaginatedAlbumCollectionHydrator());

        return $hydrators->hydrate($response, new AlbumPagination());
    }

    /**
     * @param string $id
     * @param string $country
     * @return TrackCollection
     */
    public function getArtistTopTracks($id, $country)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/artists/%s/top-tracks', $id), array(
                'query' => array(
                    'country' => $country
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new TrackCollectionHydrator());

        return $hydrators->hydrate($response, new TrackCollection());
    }

    /**
     * @param string $id
     * @return ArtistCollection
     */
    public function getArtistRelatedArtists($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/artists/%s/related-artists', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new ArtistCollectionHydrator());

        return $hydrators->hydrate($response, new ArtistCollection());
    }

    /**
     * @param string $id
     * @return Track
     */
    public function getTrack($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/tracks/%s', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new TrackHydrator());
        $hydrators->add(new AlbumAwareHydrator());
        $hydrators->add(new ArtistCollectionAwareHydrator());

        return $hydrators->hydrate($response, new Track());
    }

    /**
     * @param array $ids
     * @return TrackCollection
     */
    public function getTracks(array $ids)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', '/v1/tracks', array(
                'query' => array(
                    'ids' => implode(',', $ids)
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new TrackCollectionHydrator());

        return $hydrators->hydrate($response, new TrackCollection());
    }

    /**
     * @param string $id
     * @return User
     */
    public function getUserProfile($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/users/%s', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new UserHydrator());

        return $hydrators->hydrate($response, new User());
    }

    /**
     * @return User
     */
    public function getCurrentUser()
    {
        $response = $this->sendRequest($this->guzzleClient->createRequest('GET', '/v1/me'))->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new UserHydrator());
        $hydrators->add(new ImageCollectionAwareHydrator());

        return $hydrators->hydrate($response, new User());
    }

    /**
     * @param string $id
     * @param string $userId
     * @param array $fields
     * @return Playlist
     */
    public function getPlaylist($id, $userId, array $fields = array())
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/users/%s/playlists/%s', $userId, $id), array(
                'query' => array(
                    'fields' => implode(',', $fields)
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new PlaylistHydrator());
        $hydrators->add(new ImageCollectionAwareHydrator());
        $hydrators->add(new OwnerAwareHydrator());
        $hydrators->add(new PaginatedPlaylistTrackCollectionAwareHydrator());

        return $hydrators->hydrate($response, new Playlist());
    }

    /**
     * @param string $id
     * @param string $userId
     * @param array $fields
     * @return PlaylistTrackPagination
     */
    public function getPlaylistTracks($id, $userId, array $fields = array())
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/users/%s/playlists/%s/tracks', $userId, $id), array(
                'query' => array(
                    'fields' => implode(',', $fields)
                )
            ))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new PaginationHydrator());
        $hydrators->add(new PaginatedPlaylistTrackCollectionHydrator());

        return $hydrators->hydrate($response, new PlaylistTrackPagination());
    }

    /**
     * @param string $id
     * @return PlaylistPagination
     */
    public function getUserPlaylists($id)
    {
        $response = $this->sendRequest(
            $this->guzzleClient->createRequest('GET', sprintf('/v1/users/%s/playlists', $id))
        )->json();

        $hydrators = new AggregateHydrator();
        $hydrators->add(new PaginationHydrator());
        $hydrators->add(new PaginatedPlaylistCollectionHydrator());

        return $hydrators->hydrate($response, new Pagination());
    }

    private function sendRequest(GuzzleHttp\Message\RequestInterface $request)
    {
        try {
            return $this->guzzleClient->send($request);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            switch ($e->getResponse()->getStatusCode()) {
                case 401:
                    throw new AccessTokenExpiredException();
                    break;
                default:
                    throw new \Exception(sprintf('A problem occurred: %s', $e->getMessage()));
                    break;
            }

        } catch (\Exception $e) {
            var_dump($e);

            return null;
        }
    }
}
