<?php

class WavInfo {
    protected $fn;
    protected $fh;
    protected $samplerate=0;
    protected $channels;
    protected $bitspersample;
    private $bpf;                   // bytes per frame. a frame is a set of samples, one for each channel. bpf=bytesperample * channels
    private $frames=0;
    private $processed=FALSE;

    public function __construct($fn) {
        $this->fn=$fn;
        $this->fh=fopen($fn,'rb');
        if ($this->fh===FALSE) throw new Exception("$fn: failed to fopen");
        $this->walkRIFF();
    }

    private function fread($length) {
        $data=fread($this->fh,$length);
        if ($data===FALSE) throw new Exception("$this->fn: fread failed");
        if (strlen($data)!=$length) {
            $l=strlen($data);
            throw new Exception("$this->fn: fread read $l bytes instead of $length");
        }
        return $data;
    }

    public function getSampleRate() {return $this->samplerate;}
    public function getChannels() {return $this->channels;}
    public function getBits() {return $this->bitspersample;}
    public function getSamples() {return $this->frames;}
    public function getDuration() {return $this->getSamples()/$this->getSampleRate();}
    public function describe () {
        return("$this->samplerate Hz $this->bitspersample bit, $this->channels channels");
    }
    private function handleChunk (&$ckstack) {
        /*
        $path=array();
        foreach ($ckstack as $ck) {
            if ($ck['ckid']=='RIFF' || $ck['ckid']=='LIST') $path[]="{$ck['ckid']}({$ck['listtype']})";
			else $path[]=$ck['ckid'];
		}
        print(implode(' / ',$path)."\r\n");
         */
        $ck=$ckstack[count($ckstack)-1];
        $ckid=$ck['ckid'];
        $cklen=$ck['cklen'];
        if (count($ckstack)==2 && $ckstack[0]['ckid']=='RIFF' && $ckid=='fmt ') {
            if ($this->samplerate) throw new Exception("$this->fn: multiple fmt chunks");
            if ($cklen<14) throw new Exception("$this->fn: fmt chunk too small: $cklen");
            $data=$this->fread($cklen);
            $fmtbase=substr($data,0,14);
            $fmtext=substr($data,14);
            $fmt=unpack('vformat/vchannels/Vsamplerate/Vavbps/vblockalign',$fmtbase);
            $this->samplerate=$samplerate=$fmt['samplerate'];
            $this->channels=$channels=$fmt['channels'];
            $formattag=$fmt['format'];
            if ($formattag!=1) throw new Exception("$this->fn: unknown wFormatTag: $formattag");
            $fmt2=unpack('vbitspersample',$fmtext);
            $this->bitspersample=$bits=$fmt2['bitspersample'];
            $bytes=(int)($bits/8);
            $bytes += ($bits&3) ? 1:0;
            $this->bpf=$bytes*$channels;
            //print $this->fn.': '.$this->describe()."\n";
        }
        if (count($ckstack)==2 && $ckstack[0]['ckid']=='RIFF' && $ckid=='data') {
            if (!$this->samplerate) throw new Exception("$this->fn: no fmt found before data");
            $this->frames+=$cklen/$this->bpf;
        }
        if (count($ckstack)==3 
            && $ckstack[0]['ckid']=='RIFF' 
            && $ckstack[1]['ckid']=='LIST'
            && $ckstack[1]['listtype']=='wavl'
            && $ckid=='data'
        ) {
            if (!$this->samplerate) throw new Exception("$this->fn: no fmt found before data");
            $this->frames+=$cklen/$this->bpf;
        }
        if (count($ckstack)==3 
            && $ckstack[0]['ckid']=='RIFF' 
            && $ckstack[1]['ckid']=='LIST'
            && $ckstack[1]['listtype']=='wavl'
            && $ckid=='slnt'
        ) {
            if (!$this->samplerate) throw new Exception("$this->fn: no fmt found before data");
            $sdata=unpack('Vss',$this->fread(4));
            $ss=$sdata['ss'];
            $this->frames+=$ss;
        }
    }
    private function walkRIFF() {
        if (fseek($this->fh,0,SEEK_END)!=0) throw new Exception("$this->fn: could not seek");
        $filesize=ftell($this->fh);
        if (fseek($this->fh,0,SEEK_SET)!=0) throw new Exception("$this->fn: could not seek");

        $hdr=$this->fread(8);
        $hdra=unpack('a4ckid/Vcklen',$hdr);
        $id=$hdra['ckid'];
        $len=$hdra['cklen'];
        $dpos=ftell($this->fh);

        if ($id!='RIFF') throw new Exception("$this->fn: not a RIFF file");
        if ($len<=4) throw new Exception("$this->fn: RIFF size too small");
        if ($len>($filesize-8)) throw new Exception ("$this->fn: corrupt file: RIFF chunk bigger than file itself");
        $rifftype=$this->fread(4);
        if ($rifftype!='WAVE') throw new Exception("$this->fn: unknown RIFF format: $rifftype");
        $pos=ftell($this->fh);

        $ckstack=array();
        $thisck=array(
            'ckid' => $id,
            'cklen' => $len,
            'ckdpos' => $dpos,
            'pos' => $pos,
            'cknextpos' => $dpos+$len+($len&1),            // $len&1 is number of padding bytes: if cklen is odd then there is one padding byte
            'listtype'=>$rifftype
            );
        $ckstack[]=$thisck;
        unset($ck);
        $ck =& $ckstack[count($ckstack)-1];
        // $ck must reference only RIFF or LIST chunk
        while (TRUE) {
            //var_dump($ck);
            //print("chunk loop\n");
            if (($ck['pos']+4)>=$ck['cknextpos']) { // ignore max 4 byte of junk at the end of a container chunk
                if ($ck['pos']>$ck['cknextpos']) throw new Exception("$this->fn: corrupt file: a subchunk extends outside it's parent");
                array_pop($ckstack);
                if (count($ckstack)==0) break;          // phew, we are done
                if ($ck['pos']!=$ck['cknextpos']) fseek($this->fh,$ck['cknextpos']);      // skip over max 4 bytes of junk
                unset($ck);
                $ck =& $ckstack[count($ckstack)-1];
                //print("Going up\n");
                continue;
            }

            $ckhdr=$this->fread(8);
            $ckhdra=unpack('a4ckid/Vcklen',$ckhdr);
            $ckid=$ckhdra['ckid'];
            $cklen=$ckhdra['cklen'];
            $ckdpos=ftell($this->fh);
            unset($thisck);
            $thisck=array(
                'ckid' => $ckid,
                'cklen' => $cklen,
                'ckdpos' => $ckdpos,
                'pos' => $ckdpos,
                'cknextpos' => $ckdpos+$cklen+($cklen&1)              // $cklen&1 for padding
                );
            $ckstack[]=$thisck;
            $thisck =& $ckstack[count($ckstack)-1];

            if ($ckid == 'RIFF' || $ckid == 'LIST') {
                if ($ckid=='RIFF') throw new Exception("$this->fn: RIFF chunk not at top level");
                $listtype=$this->fread(4);
                if ($cklen<4) throw new Exception("$this->fn: LIST($listtype) chunk too small: $cklen");
                $thisck['pos']=ftell($this->fh);
                $thisck['listtype']=$listtype;
                unset($ck);
                $ck =& $ckstack[count($ckstack)-1]; // make the while work on this chunk's subchunks
                //print("Going down\n");
                continue;
            }
            $this->handleChunk($ckstack);

            $npos=$thisck['cknextpos'];
            unset($thisck);
            array_pop($ckstack);
            fseek($this->fh,$npos);
            foreach ($ckstack as &$cki) $cki['pos']=$npos;
        }
        fclose($this->fh);
        if (!$this->samplerate || !$this->frames) throw new Exception("$this->fn: analyzing failed");
        $this->processed=TRUE;
    }
}
