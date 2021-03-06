<?php

namespace Repositories;

use Doctrine\ORM\EntityRepository;

use Entities\{
    Infrastructure as InfraEntity,
    PhysicalInterface as PIEntity,
    VirtualInterface as VIEntity
};
use IXP\Exceptions\GeneralException;

/**
 * VirtualInterface
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class VirtualInterface extends EntityRepository
{
    
    /**
     * Utility function to provide a count of different customer types as `type => count`
     * where type is as defined in Entities\Customer::$CUST_TYPES_TEXT
     *
     * @return array Number of customers of each customer type as `[type] => count`
     */
    public function getByLocation()
    {
        return $ints = $this->getEntityManager()->createQuery(
            "SELECT vi.id AS id, pi.speed AS speed, i.name AS infrastructure, l.name AS locationname, sixp.name as ixp, sixp.shortname as locixp
                FROM Entities\\VirtualInterface vi
                    JOIN vi.Customer c
                    JOIN vi.PhysicalInterfaces pi
                    JOIN pi.SwitchPort sp
                    JOIN sp.Switcher sw
                    JOIN sw.Infrastructure i
                    JOIN i.IXP sixp
                    JOIN sw.Cabinet ca
                    JOIN ca.Location l
                WHERE
                    " . Customer::DQL_CUST_EXTERNAL
        )->getArrayResult();
    }


    /**
     * Utility function to provide an array of all virtual interfaces on a given
     * infrastructure (optionally with active VLAN Interfaces for a given protocol).
     *
     * Returns an array of:
     *
     *     * Customer ID (cid)
     *     * Customer Name (cname)
     *     * Customer Shortname (cshortname)
     *     * VirtualInterface ID (id)
     *     * Physical Interface ID (pid)
     *     * VLAN Interface ID (vlanid)
     *     * SwithPort ID (spid)
     *     * Switch ID (swid)
     *
     * @param \Entities\Infrastructure $infra The infrastructure to gather VirtualInterfaces for
     * @param int $proto Either 4 or 6 to limit the results to interface with IPv4 / IPv6
     * @param bool $externalOnly If true (default) then only external (non-internal) interfaces will be returned
     * @param bool $useResultCache If true, use Doctrine's result cache to prevent needless database overhead
     * @return array As defined above.
     * @throws \IXP_Exception
     */
    public function getForInfrastructure( $infra, $proto = false, $externalOnly = true, $useResultCache = true )
    {
        $qstr = "SELECT c.id AS cid, c.name AS cname, c.shortname AS cshortname,
                       vi.id AS id, pi.id AS pid, vli.id AS vlanid, sp.id AS spid, sw.id as swid
                    FROM Entities\\VirtualInterface vi
                        JOIN vi.Customer c
                        JOIN vi.PhysicalInterfaces pi
                        JOIN vi.VlanInterfaces vli
                        JOIN pi.SwitchPort sp
                        JOIN sp.Switcher sw
                        JOIN sw.Infrastructure i
                    WHERE
                        i = :infra
                        AND " . Customer::DQL_CUST_ACTIVE     . "
                        AND " . Customer::DQL_CUST_CURRENT    . "
                        AND " . Customer::DQL_CUST_TRAFFICING . "
                        AND pi.status = " . \Entities\PhysicalInterface::STATUS_CONNECTED;
                               
        if( $proto )
        {
            if( !in_array( $proto, [ 4, 6 ] ) )
                throw new \IXP_Exception( 'Invalid protocol specified' );
            
            $qstr .= "AND vli.ipv{$proto}enabled = 1 ";
        }
            
        if( $externalOnly )
            $qstr .= "AND " . Customer::DQL_CUST_EXTERNAL;
                    
        $qstr .= " ORDER BY c.name ASC";
        
        $q = $this->getEntityManager()->createQuery( $qstr );
        $q->setParameter( 'infra', $infra );
        $q->useResultCache( $useResultCache, 3600 );
        return $q->getArrayResult();
    }

    /**
     * Utility function to provide an array of all virtual interface objects on a given
     * infrastructure
     *
     * @param InfraEntity $infra The infrastructure to gather VirtualInterfaces for
     * @param int $proto Either 4 or 6 to limit the results to interface with IPv4 / IPv6
     * @param bool $externalOnly If true (default) then only external (non-internal) interfaces will be returned
     * @return array As defined above.
     * @throws \IXP_Exception
     */
    public function getObjectsForInfrastructure( InfraEntity $infra, $proto = false, $externalOnly = true )
    {
        $qstr = "SELECT vi
                    FROM Entities\VirtualInterface vi
                        JOIN vi.Customer c
                        JOIN vi.VlanInterfaces vli
                        JOIN vi.PhysicalInterfaces pi
                        JOIN pi.SwitchPort sp
                        JOIN sp.Switcher sw
                        JOIN sw.Infrastructure i
                    WHERE
                        i = :infra
                        AND " . Customer::DQL_CUST_ACTIVE     . "
                        AND " . Customer::DQL_CUST_CURRENT    . "
                        AND " . Customer::DQL_CUST_TRAFFICING . "
                        AND pi.status = " . PIEntity::STATUS_CONNECTED;

        if( $proto ) {
            if( !in_array( $proto, [ 4, 6 ] ) )
                throw new \IXP_Exception( 'Invalid protocol specified' );

            $qstr .= " AND vli.ipv{$proto}enabled = 1 ";
        }

        if( $externalOnly ) {
            $qstr .= " AND " . Customer::DQL_CUST_EXTERNAL;
        }

        $qstr .= " ORDER BY c.name ASC";

        $q = $this->getEntityManager()->createQuery( $qstr );
        $q->setParameter( 'infra', $infra );
        return $q->getResult();
    }

    /**
     * For the given $vi, we want to ensure its channel group is unique
     * within a switch
     *
     * @param VIEntity $vi
     * @return bool
     * @throws GeneralException
     */
    public function validateChannelGroup( VIEntity $vi ): bool {

        if( !$vi->getChannelgroup() ) {
            throw new GeneralException("Should not be testing a null / zero channel group number");
        }

        if( count( $vi->getPhysicalInterfaces() ) == 0 ) {
            throw new GeneralException("Channel group number is only relevant when there is at least one physical interface");
        }

        // not sure if we're supporting multi-chassis LAGs. May work, may not. Let's be positive and assume it works and account for that:
        $switches = [];

        /** @var \Entities\PhysicalInterface $pi */
        foreach( $vi->getPhysicalInterfaces() as $pi ) {
            if( !in_array( $pi->getSwitchPort()->getSwitcher()->getId(), $switches ) ) {
                $switches[] = $pi->getSwitchPort()->getSwitcher()->getId();
            }
        }

        /** @var VIEntity[] $vis */
        $vis = $this->getEntityManager()->createQuery("
                    SELECT vi FROM Entities\VirtualInterface vi
                        JOIN vi.PhysicalInterfaces pi
                        JOIN pi.SwitchPort sp
                        JOIN sp.Switcher s 
                    WHERE 
                        vi.channelgroup = :cg
                        AND s.id IN ( :switches )
                ")
            ->setParameter('cg',       $vi->getChannelgroup())
            ->setParameter('switches', $switches )
            ->getResult();

        if( count( $vis ) == 0 ) {
            return true;
        }

        foreach( $vis as $v ) {
            if( $v->getId() != $vi->getId() ) {
                return false;
            }
        }

        return true;
    }

    /**
     * For the given $vi, assign a unique channel group
     *
     * @param VIEntity $vi
     * @return int
     * @throws GeneralException
     */
    public function assignChannelGroup( VIEntity $vi ): int {

        if( count( $vi->getPhysicalInterfaces() ) == 0 ) {
            throw new GeneralException("Channel group number is only relevant when there is at least one physical interface");
        }

        // FIXME: need a more reasonbale way of doing this but I also want to ensure old group IDs get reused
        //        as many switches have an upper limit that is quite small
        $orig = $vi->getChannelgroup();
        for( $i = 1; $i < 1000; $i++ ) {
            $vi->setChannelgroup($i);
            if( $this->validateChannelGroup($vi) ) {
                $vi->setChannelgroup($orig);
                return $i;
            }
        }

        $vi->setChannelgroup($orig);
        throw new GeneralException("Could not assign a free channel group number");
    }

}
