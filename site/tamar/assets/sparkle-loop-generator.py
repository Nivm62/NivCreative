import numpy as np, math
from PIL import Image
W,H=560,795
N=60            # frames, 10 fps -> 6 s loop
yy,xx=np.mgrid[0:H,0:W].astype(np.float32)
def halo(cx,cy,sig,col,peak,t,rad,phase,period=1,breath=0.2):
    a=2*math.pi*(period*t/N)+phase
    x=cx+rad*math.cos(a); y=cy+rad*math.sin(a)*0.8
    k=peak*(1-breath+breath*(0.5+0.5*math.sin(a*2)))
    al=k*np.exp(-(((xx-x)**2+(yy-y)**2)/(2*sig*sig)))
    return al,col
def star(cx,cy,s,col,t,per,phase,drift=0):
    a=2*math.pi*(per*t/N)+phase
    tw=(0.5+0.5*math.sin(a))**2
    sc=s*(0.3+0.7*tw); k=0.15+0.85*tw
    ox=cx+drift*math.cos(a/ per if per else 0); oy=cy+drift*math.sin(a/ per if per else 0)
    x0,x1=int(max(0,ox-sc*3)),int(min(W,ox+sc*3)); y0,y1=int(max(0,oy-sc*3)),int(min(H,oy+sc*3))
    if x1<=x0 or y1<=y0: return None
    X=xx[y0:y1,x0:x1]-ox; Y=yy[y0:y1,x0:x1]-oy
    th=max(sc*0.11,0.9)
    v=np.exp(-((X/sc)**2+(Y/th)**2))+np.exp(-((X/th)**2+(Y/sc)**2))+0.7*np.exp(-((X*X+Y*Y)/(2*(sc*0.28)**2)))
    return np.clip(v*k,0,1),col,(x0,x1,y0,y1)
WHITE=(255,255,255); GOLD=(255,226,160); ROSE=(255,196,232); LAV=(214,184,255)
HALOS=[(0.72*W,0.12*H,120,WHITE,0.55,26,0.0,1),(0.12*W,0.38*H,135,ROSE,0.45,30,2.1,1),(0.78*W,0.64*H,130,LAV,0.42,28,4.0,1),(0.40*W,0.52*H,110,WHITE,0.30,22,1.0,1)]
STARS=[(0.14,0.15,12,WHITE,1,0.0),(0.84,0.11,15,GOLD,2,1.3),(0.92,0.31,9,WHITE,1,2.4),(0.06,0.36,11,GOLD,2,0.7),(0.24,0.50,10,WHITE,3,3.0),
       (0.80,0.47,14,WHITE,1,4.1),(0.37,0.11,8,GOLD,3,5.0),(0.94,0.62,11,WHITE,2,2.0),(0.05,0.58,13,GOLD,1,3.6),(0.58,0.08,9,WHITE,2,0.4),
       (0.30,0.28,7,WHITE,3,1.9),(0.70,0.33,8,GOLD,2,5.5),(0.50,0.45,6,WHITE,3,4.4),(0.18,0.70,9,WHITE,1,2.8),(0.88,0.74,10,GOLD,2,0.9)]
def sm(v): 
    v=np.clip(v,0,1); return v*v*(3-2*v)
FEATHER=(sm(np.minimum(xx,W-1-xx)/(0.14*W))*sm(np.minimum(yy,H-1-yy)/(0.10*H))).astype(np.float32)
frames=[]
for t in range(N):
    A=np.zeros((H,W),np.float32); C=np.zeros((H,W,3),np.float32)
    def add(al,col,box=None):
        global A,C
        if box is None:
            sl=(slice(0,H),slice(0,W))
        else:
            x0,x1,y0,y1=box; sl=(slice(y0,y1),slice(x0,x1))
        a=al.astype(np.float32)
        C[sl]+= a[...,None]*np.array(col,np.float32)
        A[sl]=1-(1-A[sl])*(1-a)
    for cx,cy,sig,col,pk,rad,ph,per in HALOS:
        al,c=halo(cx,cy,sig,col,pk,t,rad,ph,per); add(al,c)
    for fx,fy,s,col,per,ph in STARS:
        r=star(fx*W,fy*H,s,col,t,per,ph,drift=5)
        if r: add(r[0],r[1],r[2])
    A=np.clip(A,0,1)
    rgb=np.where(A[...,None]>1e-4,C/np.maximum(np.sum(1,keepdims=False)*0+ (A[...,None]*0+ (np.maximum(C.max(axis=2,keepdims=True),1e-3)/255.0)) ,1e-3),0) if False else None
    # simple: normalise colour by accumulated weight
    wsum=np.zeros((H,W),np.float32)
    rgbn=np.clip(C/np.maximum(C.max(axis=2,keepdims=True),1e-3)*255,0,255)
    A=A*FEATHER
    out=np.dstack([rgbn,A*255]).astype(np.uint8)
    frames.append(Image.fromarray(out,'RGBA'))
frames[0].save('sparkle-loop-v2.webp',save_all=True,append_images=frames[1:],duration=100,loop=0,lossless=False,quality=60,alpha_quality=70,method=4,minimize_size=True)
import os;print(os.path.getsize('sparkle-loop-v2.webp')/1024,'KB')
frames[0].save('f0.png');frames[30].save('f30.png')
